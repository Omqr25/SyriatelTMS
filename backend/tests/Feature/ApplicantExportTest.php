<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Jobs\ProcessApplicantExport;
use App\Models\Application;
use App\Models\Role;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Training\ApplicantExport\ExportFiles;
use App\Services\Training\ApplicantExport\ExportQueue;
use App\Services\Training\ApplicantExport\ExportStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use ZipArchive;

class ApplicantExportTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    private TrainingProgram $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/applicant-export-'.Str::ulid();
        config([
            'applicant_exports.root' => $this->root,
            'applicant_exports.min_free_bytes' => 0,
            'applicant_exports.storage_bytes' => 32 * 1024 * 1024,
            'applicant_exports.part_bytes' => 11500,
            'jwt.secret' => str_repeat('test-export-secret-', 6),
            'app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'filesystems.disks.private' => ['driver' => 'local', 'root' => $this->root.'/originals'],
        ]);
        File::ensureDirectoryExists($this->root.'/originals');
        Bus::fake([ProcessApplicantExport::class]);
        $this->program = TrainingProgram::factory()->create();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function user(string $role = 'HR'): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['role' => $role]));

        return $user;
    }

    private function endpoint(): string
    {
        return "/api/v1/training_program/{$this->program->id}/applicant-exports";
    }

    private function applicant(ApplicationStatus $status = ApplicationStatus::ACCEPTED, array $fields = []): Application
    {
        return Application::factory()->forProgram($this->program)->create($fields + [
            'status' => $status, 'cv' => '', 'national_id' => '', 'academic_qualification' => null, 'personal_photo' => '',
        ]);
    }

    private function document(Application $applicant, string $category, string $field, string $value): string
    {
        $path = 'applications/'.Str::uuid()."/$category/original.".($category === 'photo' ? 'png' : 'pdf');
        Storage::disk('private')->put($path, $value);
        $applicant->update([$field => $path]);

        return $path;
    }

    private function start(string $kind, ?string $snapshot = null, bool $fresh = false): string
    {
        return $this->postJson($this->endpoint(), ['kind' => $kind, 'snapshot_id' => $snapshot, 'fresh' => $fresh])
            ->assertAccepted()->json('id');
    }

    private function process(string $snapshot, string $kind, int $part = 0): object
    {
        $task = ExportStore::tasks()->where('export_id', $snapshot)->where('kind', $kind)->where('part', $part)->firstOrFail();
        app(ExportQueue::class)->process($task->id);

        return ExportStore::tasks()->find($task->id);
    }

    public function test_eligibility_frozen_workbook_values_and_explicit_text(): void
    {
        $this->actingAs($this->user(), 'api');
        $accepted = $this->applicant(fields: [
            'full_name' => 'سارة الشام', 'phone_number' => '00012345', 'home_address' => '=1+2', 'additional_info' => null,
        ]);
        $active = $this->applicant(ApplicationStatus::ACTIVE);
        $this->applicant(ApplicationStatus::PENDING);
        $this->applicant(ApplicationStatus::REJECTED);
        Application::factory()->forProgram(TrainingProgram::factory()->create())->accepted()->create();

        $id = $this->start('excel');
        $this->assertSame($id, $this->start('documents'));
        $this->assertSame($id, $this->start('capture', fresh: true));
        $this->assertSame('capture', ExportStore::exports()->find($id)->operation);
        $accepted->update(['full_name' => 'New Name', 'phone_number' => '999', 'status' => ApplicationStatus::REJECTED]);
        $this->applicant();
        $this->assertSame('ready', $this->process($id, 'capture')->state);
        $file = $this->process($id, 'excel');
        $this->assertSame('ready', $file->state, $file->error ?? '');
        $this->assertSame(['capture', 'excel'], ExportStore::tasks()->where('export_id', $id)->orderBy('id')->pluck('kind')->all());
        Bus::assertDispatched(ProcessApplicantExport::class, fn ($job) =>
            $job->connection === 'applicant_exports' && $job->queue === 'applicant-exports');

        $workbook = IOFactory::load(app(ExportFiles::class)->directory($id).'/'.$file->file);
        $worksheet = $workbook->getActiveSheet();
        $columns = array_values(array_diff(Schema::getColumnListing('applications'), array_keys(ExportStore::DOCUMENTS)));
        $rows = $worksheet->toArray();
        $this->assertSame($columns, $rows[3]);
        $this->assertSame('سارة الشام', $rows[4][array_search('full_name', $columns)]);
        $this->assertSame('00012345', $rows[4][array_search('phone_number', $columns)]);
        $formula = $worksheet->getCell([array_search('home_address', $columns) + 1, 5]);
        $this->assertSame('=1+2', $formula->getValue());
        $this->assertSame(DataType::TYPE_STRING, $formula->getDataType());
        $this->assertNull($rows[4][array_search('additional_info', $columns)]);
        $this->assertSame([(string) $accepted->id, (string) $active->id], [$rows[4][0], $rows[5][0]]);
        $workbook->disconnectWorksheets();

        $new = $this->start('capture', fresh: true);
        $this->assertNotSame($id, $new);
        $this->assertSame(2, (int) ExportStore::exports()->find($new)->applicant_count);
    }

    public function test_independent_zip_parts_preserve_paths_bytes_grouping_and_soft_cap(): void
    {
        $this->actingAs($this->user(), 'api');
        $a = $this->applicant(fields: ['full_name' => str_repeat('دمشق/', 20).' ..']);
        $b = $this->applicant();
        $c = $this->applicant();
        $d = $this->applicant();
        $original = $this->document($a, 'cv', 'cv', str_repeat('a', 2000));
        $this->document($a, 'photo', 'personal_photo', str_repeat('q', 1000));
        $this->document($b, 'cv', 'cv', str_repeat('b', 7200));
        $this->document($c, 'cv', 'cv', str_repeat('c', 12000));
        $this->document($d, 'cv', 'cv', str_repeat('d', 4000));
        $id = $this->start('documents');
        $this->assertSame('ready', $this->process($id, 'capture')->state);
        Storage::disk('private')->delete($original);
        $this->document($a, 'cv', 'cv', 'NEW');
        $this->assertSame('ready', $this->process($id, 'plan')->state);
        $rows = ExportStore::rows($id)->orderBy('application_id')->get();
        $this->assertSame([1, 2, 3, 4], $rows->pluck('part')->map(fn ($part) => (int) $part)->all());

        foreach ([1, 2, 3, 4] as $part) {
            $task = $this->process($id, 'zip', $part);
            $this->assertSame('ready', $task->state, $task->error ?? '');
            $this->assertSame($task->file, $this->process($id, 'zip', $part)->file);
            $archive = new ZipArchive;
            $path = app(ExportFiles::class)->directory($id).'/'.$task->file;
            $this->assertTrue($archive->open($path, ZipArchive::CHECKCONS));
            $this->assertSame($task->sha256, hash_file('sha256', $path));
            $manifest = json_decode($archive->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($part, $manifest['part']);
            $this->assertSame($part === 3 ? ['applicant_exceeds_part_target'] : [], $manifest['warnings']);
            $this->assertCount(1, $manifest['applicants']);
            if ($part === 1) {
                $docs = $manifest['applicants'][0]['documents'];
                $this->assertStringStartsWith($a->id.'_دمشق_', $docs['cv']['entry']);
                $this->assertStringEndsWith('/CV.pdf', $docs['cv']['entry']);
                $this->assertTrue(mb_check_encoding($docs['cv']['entry'], 'UTF-8'));
                $this->assertLessThan(110, strlen($docs['cv']['entry']));
                $this->assertSame(str_repeat('a', 2000), $archive->getFromName($docs['cv']['entry']));
                $this->assertSame(ZipArchive::CM_STORE, $archive->statName($docs['cv']['entry'])['comp_method']);
                $this->assertSame(str_repeat('q', 1000), $archive->getFromName($docs['personal_photo']['entry']));
                $this->assertSame('missing', $docs['national_id']['status']);
            }
            $archive->close();
        }
        $this->get($this->endpoint()."/$id/manifest")->assertOk();
        $this->getJson($this->endpoint())->assertJsonPath('data.0.document_warnings', 11)->assertJsonPath('data.0.documents_state', 'ready');
    }

    public function test_empty_cohorts_and_empty_documents_do_not_enqueue_archives(): void
    {
        $this->actingAs($this->user(), 'api');
        $empty = $this->start('documents');
        $this->assertSame('empty', ExportStore::exports()->find($empty)->documents_state);
        $this->assertSame(0, ExportStore::tasks()->count());
        $this->applicant();
        $id = $this->start('capture', fresh: true);
        $this->process($id, 'capture');
        $this->assertSame($id, $this->start('documents', $id));
        $this->process($id, 'plan');
        $this->assertSame('empty', ExportStore::exports()->find($id)->documents_state);
        $this->assertSame(0, ExportStore::tasks()->where('kind', 'zip')->count());
    }

    public function test_failed_capacity_interrupted_worker_cancel_retry_and_cleanup(): void
    {
        $this->actingAs($this->user(), 'api');
        $applicant = $this->applicant();
        $original = $this->document($applicant, 'cv', 'cv', 'original');
        $id = $this->start('documents');
        $this->process($id, 'capture');
        $this->process($id, 'plan');
        config(['applicant_exports.storage_bytes' => 1]);
        $task = $this->process($id, 'zip', 1);
        $this->assertSame('storage_exhausted', $task->error);
        config(['applicant_exports.storage_bytes' => 32 * 1024 * 1024]);
        $this->postJson($this->endpoint()."/$id/retry")->assertAccepted();
        ExportStore::tasks()->where('id', $task->id)->update([
            'state' => 'running', 'owner' => 'interrupted', 'lease_until' => now()->subSecond(), 'attempts' => 1,
        ]);
        $directory = app(ExportFiles::class)->directory($id);
        File::put("$directory/attempt-$task->id-interrupted", 'incomplete');
        app(ExportQueue::class)->reconcile();
        $this->assertSame('worker_interrupted', ExportStore::tasks()->find($task->id)->error);
        $this->postJson($this->endpoint()."/$id/cancel")->assertNoContent();
        $this->assertSame('cancelled', $this->process($id, 'zip', 1)->state);
        $this->postJson($this->endpoint()."/$id/retry")->assertAccepted();
        $this->assertSame('ready', $this->process($id, 'zip', 1)->state);
        $this->assertFileDoesNotExist("$directory/attempt-$task->id-interrupted");
        $this->travel(25)->hours();
        app(ExportQueue::class)->reconcile();
        $download = app(ExportFiles::class)->lock($id);
        $this->assertSame('queued', $this->process($id, 'prune')->state);
        fclose($download);
        $this->travel(16)->seconds();
        $taskId = ExportStore::tasks()->where('export_id', $id)->where('kind', 'prune')->value('id');
        app(ExportQueue::class)->process($taskId);
        $this->assertNull(ExportStore::exports()->find($id));
        $this->assertDirectoryDoesNotExist($directory);
        $this->assertTrue(Storage::disk('private')->exists($original));
    }

    public function test_running_zip_cannot_publish_after_cancellation(): void
    {
        $this->actingAs($this->user(), 'api');
        $this->document($this->applicant(), 'cv', 'cv', 'original');
        $id = $this->start('documents');
        $this->process($id, 'capture');
        $this->process($id, 'plan');

        $files = new class($this->program) extends ExportFiles
        {
            public function __construct(private TrainingProgram $program) {}

            public function zip(object $export, int $part, bool $oversized, string $path, callable $checkpoint): void
            {
                parent::zip($export, $part, $oversized, $path, $checkpoint);
                ExportStore::cancel($this->program, $export->id);
            }
        };
        $this->app->instance(ExportFiles::class, $files);
        $task = $this->process($id, 'zip', 1);
        $this->assertSame('cancelled', $task->state);
        $this->assertNull($task->file);
        $this->assertEmpty(File::glob($files->directory($id).'/attempt-*'));
    }

    public function test_modified_pin_and_unsafe_document_path_cannot_be_published(): void
    {
        $this->actingAs($this->user(), 'api');
        $applicant = $this->applicant(fields: ['national_id' => '../national-id.pdf']);
        $path = $this->document($applicant, 'cv', 'cv', 'original');
        $id = $this->start('documents');
        $this->process($id, 'capture');
        $this->process($id, 'plan');
        $docs = json_decode(ExportStore::rows($id)->first()->documents, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('invalid_path', $docs['national_id']['status']);

        file_put_contents(Storage::disk('private')->path($path), 'modified');
        $task = $this->process($id, 'zip', 1);
        $this->assertSame('document_changed', $task->error);
        $this->assertSame('failed', $task->state);
        $this->assertNull($task->file);
    }

    public function test_range_download_requires_current_hr_and_renewal_preserves_the_artifact(): void
    {
        $hr = $this->user();
        $supervisor = $this->user('SUPERVISOR');
        $this->actingAs($supervisor, 'api');
        $this->getJson($this->endpoint())->assertForbidden();
        $this->postJson($this->endpoint(), ['kind' => 'documents'])->assertForbidden();
        $this->actingAs($hr, 'api');
        $applicant = $this->applicant();
        $this->document($applicant, 'cv', 'cv', 'download bytes');
        $id = $this->start('documents');
        foreach (['capture', 'plan'] as $kind) $this->process($id, $kind);
        $part = $this->process($id, 'zip', 1);
        $token = auth('api')->login($hr);
        $endpoint = "/api/v1/applicant-export-downloads/$id/$part->id/ticket";
        $this->post($endpoint, ['access_token' => $token], ['Origin' => 'https://unapproved.invalid'])->assertForbidden();
        $this->post($endpoint, ['access_token' => auth('api')->login($supervisor)], ['Origin' => 'http://localhost'])->assertForbidden();

        $issue = fn () => $this->post($endpoint, ['access_token' => $token], ['Origin' => 'http://localhost'])->assertStatus(303);
        $first = $issue();
        $link = $first->headers->get('Location');
        $this->assertStringStartsWith('/api/v1/applicant-export-downloads/', $link);
        $this->get($link)->assertUnauthorized();
        $cookie = $first->getCookie('tms_export', false);
        $this->assertTrue($cookie->isHttpOnly());
        $this->withUnencryptedCookie('tms_export', $cookie->getValue());
        $baseline = $this->get($link)->assertOk();
        $etag = $baseline->headers->get('ETag');
        $prefix = $this->get($link, ['Range' => 'bytes=0-15', 'If-Range' => $etag])
            ->assertStatus(206)->assertHeader('Content-Range', "bytes 0-15/$part->bytes")
            ->assertHeader('Content-Length', '16');
        $this->get($link, ['Range' => 'bytes=16-', 'If-Range' => '"stale-hash"'])->assertOk();

        $this->travel(11)->minutes();
        $this->get($link, ['Range' => 'bytes=16-', 'If-Range' => $etag])->assertForbidden();
        $renewed = $issue();
        $link = $renewed->headers->get('Location');
        $this->withUnencryptedCookie('tms_export', $renewed->getCookie('tms_export', false)->getValue());
        $suffix = $this->get($link, ['Range' => 'bytes=16-', 'If-Range' => $etag])->assertStatus(206)
            ->assertHeader('ETag', $etag)->assertHeader('Content-Range', 'bytes 16-'.($part->bytes - 1)."/$part->bytes");
        ob_start();
        try {
            $prefix->baseResponse->sendContent();
            $suffix->baseResponse->sendContent();
            $this->assertSame($part->sha256, hash('sha256', ob_get_contents()));
        } finally {
            ob_end_clean();
        }
        $this->assertSame($part->file, ExportStore::tasks()->find($part->id)->file);
        DB::table('users')->where('id', $hr->id)->increment('session_version');
        $this->get($link, ['Range' => 'bytes=16-', 'If-Range' => $etag])->assertUnauthorized();
    }

    public function test_export_queue_does_not_change_default_connection_settings(): void
    {
        $this->assertSame('default', config('queue.connections.database.queue'));
        $this->assertSame(90, config('queue.connections.database.retry_after'));
        $job = new ProcessApplicantExport(1);
        $this->assertGreaterThan($job->timeout, config('queue.connections.applicant_exports.retry_after'));
        $this->assertSame('applicant-exports', $job->queue);
        $this->assertSame('applicant_exports', $job->connection);
    }
}
