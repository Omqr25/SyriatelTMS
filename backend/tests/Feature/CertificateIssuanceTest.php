<?php

namespace Tests\Feature;

use App\Enums\CertificateState;
use App\Jobs\GenerateCertificate;
use App\Models\Certificate;
use App\Models\CertificateTemplateVersion;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Certificate\CertificateFontAssets;
use App\Services\Certificate\CertificateLifecycle;
use App\Services\Certificate\CertificateStorage;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Tests\Support\CertificateFixtures;
use Tests\Support\UsesCertificateTestDatabase;
use Tests\TestCase;

final class CertificateIssuanceTest extends TestCase
{
    use CertificateFixtures, UsesCertificateTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('T', 32)), 'jwt.secret' => str_repeat('test-secret-', 8)]);
        Storage::fake('private');
        $this->travelTo(CarbonImmutable::parse('2026-12-01 00:00:00', 'Asia/Damascus'));
    }

    public function test_pagination_selection_and_confirmation_are_program_scoped_and_idempotent(): void
    {
        $hr = $this->member('HR');
        $program = $this->program();
        $first = $this->trainee($program);
        for ($i = 1; $i < 80; $i++) {
            $this->trainee($program);
        }
        $other = $this->trainee($this->program());
        $version = $this->version($hr);
        $this->actingAs($hr, 'api')->getJson('/api/v1/certificates/issuance/options')
            ->assertOk()->assertJsonPath('data.versions.0.id', $version->id);
        $path = $this->path($program);
        $lastPage = $this->getJson($path.'/roster?page=7')
            ->assertOk()->assertJsonPath('data.counts.ready', 80)->assertJsonCount(8, 'data.roster.data')
            ->assertJsonCount(80, 'data.ready_ids')->json('data.roster.data');
        $this->getJson($path.'/roster?search='.rawurlencode($first->user->email))
            ->assertOk()->assertJsonPath('data.roster.data.0.id', $first->id)->assertJsonPath('data.roster.total', 1);
        $this->getJson($path.'/roster?status=failed')->assertOk()->assertJsonPath('data.roster.total', 0);

        $skipped = $lastPage[7]['id'];
        $ids = $this->postJson($path.'/plan', [
            'mode' => 'all', 'except_ids' => [$skipped], 'template_version_id' => $version->id,
        ])->assertOk()->assertJsonCount(79, 'data.trainee_ids')->json('data.trainee_ids');
        $this->assertNotContains($skipped, $ids);
        $this->assertNotContains($other->id, $ids);
        $payload = $this->confirmation($version, $ids);
        $batch = $this->postJson($path.'/confirm', $payload)->assertAccepted()->assertJsonPath('data.total', 79)->json('data.id');
        $this->postJson($path.'/confirm', $payload)->assertAccepted()->assertJsonPath('data.id', $batch);
        $this->postJson($path.'/confirm', $this->confirmation($version, [$first->id]))->assertUnprocessable();
        $this->assertDatabaseCount('certificate_jobs', 79);
        $this->assertDatabaseCount('certificate_batches', 1);

        // A new request with no local UI state reconstructs the batch and counts from the database.
        $this->getJson($path.'/roster')->assertOk()->assertJsonPath('data.counts.queued', 79)
            ->assertJsonPath('data.batches.0.id', $batch)->assertJsonPath('data.batches.0.total', 79)
            ->assertJsonPath('data.batches.0.issued', 0);
    }

    public function test_blocked_review_manual_names_and_scoped_roles(): void
    {
        $hr = $this->member('HR');
        $program = $this->program();
        $a = $this->trainee($program, ['application' => 'اسم', 'account' => 'حساب']);
        $b = $this->trainee($program, ['status' => 'fired']);
        $c = $this->trainee($this->program());
        $version = $this->version($hr);
        $path = $this->path($program);

        $this->getJson($path.'/roster')->assertUnauthorized();
        $this->actingAs($a->user, 'api')->getJson($path.'/roster')->assertForbidden();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00', 'Asia/Damascus'));
        $this->getJson('/api/v1/certificates/name')->assertOk()->assertJsonPath('data.state', 'scheduled');
        $this->putJson('/api/v1/certificates/name', ['name' => 'Passport Name'])->assertUnprocessable();
        $this->actingAs($hr, 'api')->postJson($path.'/name-window')->assertNoContent();
        $this->postJson($path.'/confirm', $this->confirmation($version, [$b->id]) + ['override_reason' => 'Documented exception'])
            ->assertUnprocessable()->assertJsonValidationErrors('issuance');
        $this->actingAs($a->user, 'api')->getJson('/api/v1/certificates/name')->assertOk()->assertJsonPath('data.state', 'open');
        $this->putJson('/api/v1/certificates/name', ['name' => 'Invalid 12'])->assertUnprocessable();
        $this->actingAs($c->user, 'api')->putJson('/api/v1/certificates/name', ['name' => 'Other Passport', 'trainee_id' => $a->id])
            ->assertUnprocessable(); // The other trainee has their own scheduled window.

        $this->travelTo(CarbonImmutable::parse('2026-12-01 00:00', 'Asia/Damascus'));
        $this->actingAs($hr, 'api');
        $this->postJson($path.'/plan', ['mode' => 'all', 'template_version_id' => $version->id])
            ->assertOk()->assertJsonCount(0, 'data.trainee_ids')->assertJsonPath('data.blocked.0.id', $a->id);
        $this->putJson($path.'/trainees/'.$c->id.'/name', ['name' => 'Wrong Program'])->assertUnprocessable();
        $this->putJson($path.'/trainees/'.$a->id.'/name', ['name' => 'Manual Source'])->assertNoContent();
        $this->putJson($path.'/trainees/'.$a->id.'/exclusion', ['excluded' => true, 'reason' => 'Review required'])->assertNoContent();
        $this->getJson($path.'/roster')->assertOk()->assertJsonPath('data.counts.excluded', 1)->assertJsonPath('data.counts.fired', 1);
        $this->putJson($path.'/trainees/'.$a->id.'/exclusion', ['excluded' => false, 'reason' => 'Review completed'])->assertNoContent();
        $this->postJson($path.'/confirm', $this->confirmation($version, [$a->id, $b->id]) + ['override_reason' => 'Bulk override'])
            ->assertUnprocessable();
        $this->postJson($path.'/confirm', $this->confirmation($version, [$b->id]) + ['override_reason' => 'Documented exception'])
            ->assertAccepted();
        $this->postJson($path.'/confirm', $this->confirmation($version, [$a->id]))->assertAccepted();
        $this->assertDatabaseHas('certificate_events', ['trainee_id' => $a->id, 'action' => 'restored']);
        $this->assertDatabaseHas('certificate_events', ['trainee_id' => $b->id, 'action' => 'overridden']);
        $this->assertSame('fired', $b->fresh()->status->value);
        $this->assertSame('Manual Source', Certificate::where('trainee_id', $a->id)->firstOrFail()->snapshot['trainee_name']);
        $hr->roles()->detach();
        $this->getJson($path.'/roster')->assertForbidden();
    }

    public function test_full_real_batch_partial_failure_and_retry_only_failed(): void
    {
        $hr = $this->member('HR');
        $program = $this->program();
        for ($i = 0; $i < 80; $i++) {
            $this->trainee($program);
        }
        $this->actingAs($hr, 'api');
        [$version] = $this->renderableVersion();
        $path = $this->path($program);
        $ids = $this->postJson($path.'/plan', ['mode' => 'all', 'template_version_id' => $version->id])
            ->assertOk()->assertJsonCount(80, 'data.trainee_ids')->json('data.trainee_ids');
        $this->postJson($path.'/confirm', $this->confirmation($version, $ids))->assertAccepted()->assertJsonPath('data.total', 80);
        $targets = Certificate::orderByDesc('id')->limit(3)->pluck('id');
        $disk = Storage::disk('private');
        foreach ($targets as $id) {
            $disk->put('certificates/issued/'.$id, 'storage conflict');
        }
        foreach (['jobs' => 'default', 'applicant_export_jobs' => 'applicant-exports'] as $table => $queue) {
            DB::table($table)->insert(['queue' => $queue, 'payload' => '{}', 'attempts' => 0,
                'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
        }

        $start = hrtime(true);
        $this->runDue(80);
        $this->assertSame(77, Certificate::issued()->count());
        $this->assertDatabaseCount('certificate_jobs', 3);
        $this->travel(6)->seconds();
        $this->runDue(3);
        $this->travel(16)->seconds();
        $this->runDue(3);
        $firstPass = (hrtime(true) - $start) / 1e9;
        $this->assertDatabaseCount('certificate_jobs', 0);
        $this->getJson($path.'/roster')->assertOk()->assertJsonPath('data.counts.issued', 77)
            ->assertJsonPath('data.counts.failed', 3)->assertJsonPath('data.batches.0.failed', 3);
        $before = Certificate::issued()->get()->keyBy('id')->map(fn ($item) => [$item->path, $item->generation_attempts]);
        $oldFailed = Certificate::whereKey($targets)->get()->keyBy('id');
        $this->assertSame(['storage_failed'], $oldFailed->pluck('failure_code')->unique()->values()->all());
        $this->postJson($path.'/retry', ['certificate_id' => $before->keys()->first()])->assertUnprocessable();
        foreach ($targets as $id) {
            $disk->delete('certificates/issued/'.$id);
        }
        $this->postJson($path.'/retry')->assertAccepted()->assertJsonPath('data.total', 3);
        $this->postJson($path.'/retry')->assertAccepted()->assertJsonPath('data.total', 0);
        $this->assertDatabaseCount('certificate_jobs', 3);
        $retryStart = hrtime(true);
        $this->runDue(3);
        $retryTime = (hrtime(true) - $retryStart) / 1e9;

        $this->assertSame(80, Certificate::issued()->count());
        $this->assertCount(80, $disk->allFiles('certificates/issued'));
        foreach (Certificate::issued()->get() as $cert) {
            $disk->assertExists($cert->path);
            if ($old = $oldFailed->get($cert->id)) {
                $this->assertSame($old->number, $cert->number);
                $this->assertSame($old->snapshot, $cert->snapshot);
                $this->assertSame($old->issue_date->toDateString(), $cert->issue_date->toDateString());
                $this->assertSame(4, $cert->generation_attempts);
            } else {
                $this->assertSame($before->get($cert->id), [$cert->path, $cert->generation_attempts]);
            }
        }
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('applicant_export_jobs', 1);
        $this->getJson($path.'/roster')->assertOk()->assertJsonPath('data.batches.0.issued', 80)->assertJsonPath('data.batches.0.failed', 0);
        fwrite(STDOUT, sprintf("\n80-certificate batch (86 actual renderer calls, delayed retries advanced): %.2fs; 3 recovered: %.2fs\n", $firstPass, $retryTime));
    }

    public function test_interrupted_attempt_is_fenced_and_pdf_matches_live_proof(): void
    {
        $values = CertificateFontAssets::SAMPLES['long'];
        $hr = $this->member('HR');
        $program = $this->program();
        $program->update(['name' => $values['program_name']]);
        $trainee = $this->trainee($program, ['application' => $values['trainee_name'], 'department' => $values['department_name']]);
        $this->actingAs($hr, 'api');
        [$version, $config] = $this->renderableVersion();
        $proof = $this->postJson('/api/v1/certificates/templates/proof', [
            'version_id' => $version->id, 'configuration' => $config, 'sample' => 'long',
        ])->assertOk()->getContent();
        $this->postJson($this->path($program).'/confirm', $this->confirmation($version, [$trainee->id]))->assertAccepted();
        $certificate = Certificate::firstOrFail();
        $this->assertSame($values['trainee_name'], $certificate->snapshot['values']['trainee_name']);
        $this->assertSame($values['program_name'], $certificate->snapshot['values']['program_name']);
        $this->assertSame($values['department_name'], $certificate->snapshot['values']['department_name']);
        $trainee->application->update(['full_name' => 'Changed Later']);
        $queue = Queue::connection('certificates');
        $orphan = $queue->pop('certificates');
        $lifecycle = app(CertificateLifecycle::class);
        [$stale] = $lifecycle->claim($certificate->id, $certificate->dispatch_token);
        $abandoned = app(CertificateStorage::class)->issuedPath($stale->id, $stale->attempt_token);
        Storage::disk('private')->put($abandoned.'.tmp', 'abandoned staging file');
        $this->assertNull($queue->pop('certificates'));
        $this->actingAs($trainee->user, 'api')->getJson('/api/v1/certificates')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/certificates/'.$certificate->id.'/download')->assertForbidden();
        $this->travel(config('queue.connections.certificates.retry_after') + 1)->seconds();
        $this->runDue(1);
        unset($orphan);
        $issued = $certificate->fresh();
        $this->assertSame(CertificateState::Issued, $issued->state);
        $this->assertSame(2, $issued->generation_attempts);
        Storage::disk('private')->assertMissing($abandoned.'.tmp');
        $issuedBytes = Storage::disk('private')->get($issued->path);
        // mPDF inserts a time and a random trailer document ID. All PDF rendering objects must be identical.
        $this->assertSame($this->pdfWithoutVolatileMetadata($proof), $this->pdfWithoutVolatileMetadata($issuedBytes));
        try {
            $lifecycle->publish($issued, $stale->attempt_token);
            $this->fail('An interrupted attempt cannot replace the published PDF.');
        } catch (LogicException) {
            $this->assertSame($issued->path, $issued->fresh()->path);
        }
        $queue->push((new GenerateCertificate($issued->id, $issued->dispatch_token))->beforeCommit(), '', 'certificates');
        $this->runDue(1);
        $this->assertSame(2, $issued->fresh()->generation_attempts);
        $this->getJson('/api/v1/certificates')->assertOk()->assertJsonPath('data.0.trainee_name', $values['trainee_name']);
        $this->get('/api/v1/certificates/'.$issued->id.'/download')->assertOk();
        $this->actingAs($this->member('TRAINEE'), 'api')->getJson('/api/v1/certificates/'.$issued->id.'/download')->assertForbidden();
        $archive = '/api/v1/certificates/archive/programs/'.$program->id;
        $this->actingAs($hr, 'api')->getJson($archive.'/certificates')->assertOk()
            ->assertJsonPath('data.results.data.0.number', $issued->number);
        $packageId = $this->postJson($archive.'/packages', ['format' => 'zip', 'selection' => 'all',
            'request_key' => (string) Str::uuid()])->assertAccepted()->json('data.id');
        $this->runDue(1);
        $this->get($archive.'/packages/'.$packageId.'/download')->assertOk();
        $this->assertSame($issuedBytes, Storage::disk('private')->get($issued->path));
        $this->assertSame(2, $issued->fresh()->generation_attempts);
    }

    private function pdfWithoutVolatileMetadata(string $pdf): string
    {
        return preg_replace(['~/CreationDate \(D:[^)]*\)~', '~/ModDate \(D:[^)]*\)~', '~/ID\s*\[\s*<[^>]*>\s*<[^>]*>\s*\]~'],
            ['/CreationDate (omitted)', '/ModDate (omitted)', '/ID [omitted]'], $pdf);
    }

    private function runDue(int $count): void
    {
        $worker = app('queue.worker');
        for ($i = 0; $i < $count; $i++) {
            $worker->runNextJob('certificates', 'certificates', new WorkerOptions(sleep: 0, maxTries: 3));
        }
    }

    private function path(TrainingProgram $program): string
    {
        return '/api/v1/certificates/issuance/programs/'.$program->id;
    }

    private function confirmation(CertificateTemplateVersion $version, array $ids): array
    {
        return ['template_version_id' => $version->id, 'trainee_ids' => $ids, 'request_key' => (string) Str::uuid(), 'confirmed' => true];
    }

    private function renderableVersion(): array
    {
        $image = imagecreatetruecolor(840, 1188);
        imagefill($image, 0, 0, imagecolorallocate($image, 242, 249, 252));
        ob_start();
        imagepng($image);
        $upload = $this->post('/api/v1/certificates/templates/backgrounds', [
            'file' => UploadedFile::fake()->createWithContent('background.png', ob_get_clean()),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $config = $this->configuration();
        $config['page'] = $upload['page'];
        $config['boxes'][0]['w'] = 125;
        $config['boxes'][0]['h'] = 58;
        $config['boxes'][0]['paragraphs'] = [[
            ['text' => 'This is to certify that '], ['token' => 'trainee_name', 'bold' => true],
            ['text' => ' attended '], ['token' => 'program_name', 'italic' => true], ['text' => ' in '],
            ['token' => 'department_name'], ['text' => '.'],
        ], [
            ['token' => 'certificate_number'], ['text' => ' - '], ['token' => 'issue_date'],
        ]];
        $id = $this->postJson('/api/v1/certificates/templates', [
            'name' => 'Training certificate', 'background_token' => $upload['token'], 'configuration' => $config,
        ])->assertCreated()->json('data.version_id');

        return [CertificateTemplateVersion::findOrFail($id), $config];
    }
}
