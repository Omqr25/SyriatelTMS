<?php

namespace Tests\Feature;

use App\Jobs\PackageCertificates;
use App\Models\Certificate;
use App\Models\CertificatePackage;
use App\Models\TrainingProgram;
use App\Services\Certificate\CertificatePackages;
use App\Services\Certificate\CertificatePdf;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\CertificateFixtures;
use Tests\Support\UsesCertificateTestDatabase;
use Tests\TestCase;
use ZipArchive;

final class CertificatePackagesTest extends TestCase
{
    use CertificateFixtures, UsesCertificateTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
        Storage::fake('private');
        Storage::fake('local');
    }

    public function test_archive_groups_by_program_and_searches_current_and_frozen_fields(): void
    {
        $program = $this->program();
        $other = $this->program();
        $other->update(['name' => $program->name]);
        $certificate = $this->stored($program, 1);
        for ($i = 2; $i <= 13; $i++) {
            $this->stored($program, $i);
        }
        $this->stored($other, 14);
        $hr = $this->member('HR');
        $trainee = $this->trainee($program, ['account' => 'Current External Name', 'status' => 'fired']);
        $number = 'CERT-2026-689701';
        $issued = Certificate::create(['trainee_id' => $trainee->id, 'training_program_id' => $program->id,
            'issue_date' => '2026-12-01', 'path' => 'certificates/snapshot.pdf']);
        DB::table('certificates')->where('id', $issued->id)->update([
            'number' => $number, 'snapshot' => json_encode(['trainee_name' => 'Frozen Archive Name']),
        ]);
        $pending = $this->trainee($program);
        $draft = new Certificate;
        $draft->forceFill(['trainee_id' => $pending->id, 'training_program_id' => $program->id,
            'state' => 'queued', 'issue_date' => '2026-12-01'])->save();

        $this->getJson('/api/v1/certificates/archive/programs')->assertUnauthorized();
        $this->actingAs($certificate->trainee->user, 'api')->getJson('/api/v1/certificates/archive/programs')->assertForbidden();
        $this->actingAs($hr, 'api')->getJson('/api/v1/certificates/archive/programs')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $program->id, 'issued_count' => 14])
            ->assertJsonFragment(['id' => $other->id, 'issued_count' => 1]);
        $path = $this->endpoint($program).'/certificates';
        $this->getJson($path.'?page=2')->assertOk()->assertJsonPath('data.total', 14)
            ->assertJsonCount(2, 'data.results.data')->assertJsonPath('data.results.data.1.id', $certificate->id);
        foreach (['frozen archive', $number, $trainee->id, $trainee->user->email] as $search) {
            $this->getJson($path.'?search='.urlencode((string) $search))->assertOk()->assertJsonPath('data.total', 14)
                ->assertJsonPath('data.results.total', 1)->assertJsonPath('data.results.data.0.id', $issued->id);
        }
        $this->getJson($path.'?search=inexistent')->assertOk()->assertJsonPath('data.results.total', 0);
        $this->getJson($path.'?search='.urlencode($certificate->trainee->user->email))->assertOk()
            ->assertJsonPath('data.results.data.0.number', null)->assertJsonMissingPath('data.results.data.0.path');
        $this->get('/api/v1/certificates/'.$certificate->id.'/preview')->assertOk();
        $this->get('/api/v1/certificates/'.$certificate->id.'/download')->assertDownload('certificate-'.$certificate->id.'.pdf');
    }

    public function test_eighty_sources_produce_exact_selected_and_all_packages_and_preserve_originals(): void
    {
        $hr = $this->member('HR');
        $program = $this->program();
        $program->update(['name' => 'Network / Operations']);
        $this->actingAs($hr, 'api');
        $originals = [];
        for ($i = 1; $i <= 80; $i++) {
            $certificate = $this->stored($program, $i);
            $originals[$certificate->id] = Storage::disk('local')->get($certificate->path);
        }
        $ids = array_keys($originals);
        $except = $ids[57];
        $path = $this->endpoint($program);
        $this->getJson($path.'/certificates?page=7')->assertOk()->assertJsonCount(8, 'data.results.data')->assertJsonPath('data.total', 80);

        foreach (['jobs' => 'default', 'applicant_export_jobs' => 'applicant-exports'] as $table => $queue) {
            DB::table($table)->insert(['queue' => $queue, 'payload' => '{}', 'attempts' => 0,
                'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
        }
        $zipPayload = $this->payload('zip') + ['selection' => 'all', 'except_ids' => [$except]];
        $zip = $this->postJson($path.'/packages', $zipPayload)->assertAccepted()->assertJsonPath('data.total', 79)->json('data.id');
        $this->postJson($path.'/packages', $zipPayload)->assertAccepted()->assertJsonPath('data.id', $zip);
        $this->assertDatabaseCount('certificate_jobs', 1);
        $this->getJson($path.'/packages')->assertOk()->assertJsonPath('data.0.status', 'queued');
        $zipTime = $this->work();
        $this->getJson($path.'/packages')->assertOk()->assertJsonPath('data.0.processed', 79)->assertJsonPath('data.0.status', 'ready');
        $this->get($path.'/packages/'.$zip.'/download')->assertOk()->assertDownload('certificates-program-'.$program->id.'.zip');
        $archive = new ZipArchive;
        $temporaryZip = $this->artifact($zip);
        $this->assertTrue($archive->open($temporaryZip, ZipArchive::CHECKCONS));
        $this->assertSame(79, $archive->numFiles);
        foreach (array_diff($ids, [$except]) as $id) {
            $entry = 'program-'.$program->id.'-network-operations/certificate-'.$id.'.pdf';
            $this->assertSame(ZipArchive::CM_STORE, $archive->statName($entry)['comp_method']);
            $this->assertSame($originals[$id], $archive->getFromName($entry));
        }
        $this->assertFalse($archive->locateName('program-'.$program->id.'-network-operations/certificate-'.$except.'.pdf'));
        $archive->close();

        $allZip = $this->postJson($path.'/packages', $this->payload('zip') + ['selection' => 'all'])
            ->assertAccepted()->assertJsonPath('data.total', 80)->json('data.id');
        $allZipTime = $this->work();
        $this->assertTrue($archive->open($this->artifact($allZip), ZipArchive::CHECKCONS));
        $this->assertSame(80, $archive->numFiles);
        $this->assertSame($originals[$except], $archive->getFromName('program-'.$program->id.'-network-operations/certificate-'.$except.'.pdf'));
        $archive->close();

        $pdf = $this->postJson($path.'/packages', $this->payload('pdf') + ['selection' => 'all'])
            ->assertAccepted()->assertJsonPath('data.total', 80)->json('data.id');
        $pdfTime = $this->work();
        $this->get($path.'/packages/'.$pdf.'/download')->assertOk()->assertDownload('certificates-program-'.$program->id.'.pdf');
        $temporaryPdf = $this->artifact($pdf);
        $merged = new CertificatePdf(['tempDir' => storage_path('framework/testing')]);
        $this->assertSame(81, $merged->setSourceFile($temporaryPdf));
        for ($page = 1; $page <= 80; $page++) {
            $size = $merged->getTemplateSize($merged->importPage($page));
            [$width, $height] = $this->geometry($page);
            $this->assertEqualsWithDelta($width, $size['width'] * 72 / 25.4, .005, 'Width on page '.$page);
            $this->assertEqualsWithDelta($height, $size['height'] * 72 / 25.4, .005, 'Height on page '.$page);
        }
        $merged->cleanUp(true);
        foreach (Certificate::whereKey($ids)->get() as $certificate) {
            $this->assertSame($originals[$certificate->id], Storage::disk('local')->get($certificate->path));
            $this->assertSame(0, $certificate->generation_attempts);
        }
        $this->assertDatabaseCount('certificate_jobs', 0);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('applicant_export_jobs', 1);
        if ($evidence = getenv('CERTIFICATE_TEST_EVIDENCE')) {
            copy($temporaryPdf, $evidence.'/combined.pdf');
            copy($temporaryZip, $evidence.'/selected.zip');
            foreach (array_values($originals) as $i => $original) {
                file_put_contents($evidence.'/original-'.($i + 1).'.pdf', $original);
            }
        }
        fwrite(STDOUT, sprintf("\n80-source PDF: %.3fs, %d bytes; 80-source STORE ZIP: %.3fs, %d bytes; 79-source STORE ZIP: %.3fs\n",
            $pdfTime, filesize($temporaryPdf), $allZipTime, filesize($this->artifact($allZip)), $zipTime));
    }

    public function test_failed_package_can_retry_and_expiration_cleanup_cannot_remove_certificates(): void
    {
        $hr = $this->member('HR');
        $colleague = $this->member('HR');
        $program = $this->program();
        $outside = $this->program();
        $a = $this->stored($program, 12);
        $b = $this->stored($program, 13);
        $c = $this->stored($outside, 14);
        $this->actingAs($hr, 'api');
        $path = $this->endpoint($program).'/packages';
        $this->postJson($path, $this->payload('zip') + ['selection' => 'explicit', 'certificate_ids' => [$a->id, $c->id]])->assertUnprocessable();
        $this->postJson($path, $this->payload('zip') + ['selection' => 'all', 'except_ids' => [$c->id]])->assertUnprocessable();
        $id = $this->postJson($path, $this->payload('zip') + ['selection' => 'explicit', 'certificate_ids' => [$a->id, $b->id]])
            ->assertAccepted()->json('data.id');
        $this->postJson($path, $this->payload('pdf') + ['selection' => 'all'])->assertUnprocessable();
        $this->postJson($path.'/'.$id.'/retry')->assertUnprocessable();
        $this->getJson($path.'/'.$id.'/download')->assertNotFound();
        $this->postJson($this->endpoint($outside).'/packages/'.$id.'/retry')->assertNotFound();
        $this->actingAs($colleague, 'api')->getJson($path)->assertOk()->assertJsonCount(0, 'data');
        $this->postJson($path.'/'.$id.'/retry')->assertNotFound();
        $this->getJson($path.'/'.$id.'/download')->assertNotFound();

        $this->actingAs($hr, 'api');
        $disk = Storage::disk('local');
        $original = $disk->get($b->path);
        $disk->delete($b->path);
        $this->work();
        $this->getJson($path)->assertOk()->assertJsonPath('data.0.failure_code', 'missing_source')
            ->assertJsonPath('data.0.failed_certificate_id', $b->id);
        $this->getJson($path.'/'.$id.'/download')->assertNotFound();

        $disk->put($b->path, "%PDF-1.7\ntruncated");
        $this->postJson($path.'/'.$id.'/retry')->assertAccepted();
        Queue::connection('certificates')->push((new PackageCertificates($id, 1))->beforeCommit(), '', 'certificates');
        $this->work();
        $this->work();
        $this->getJson($path)->assertOk()->assertJsonPath('data.0.failure_code', 'unsupported_source')
            ->assertJsonPath('data.0.failed_certificate_id', $b->id)->assertJsonPath('data.0.status', 'failed');
        $this->getJson($path.'/'.$id.'/download')->assertNotFound();

        $disk->put($b->path, $this->pdf(13, active: true));
        $this->postJson($path.'/'.$id.'/retry')->assertAccepted();
        $this->work();
        $this->getJson($path)->assertOk()->assertJsonPath('data.0.failure_code', 'unsupported_source')
            ->assertJsonPath('data.0.failed_certificate_id', $b->id);

        $disk->put($b->path, $original);
        $this->postJson($path.'/'.$id.'/retry')->assertAccepted();
        $this->work();
        $this->assertDatabaseHas('certificate_packages', ['id' => $id, 'status' => 'ready', 'dispatch' => 4]);
        $this->postJson($path.'/'.$id.'/retry')->assertUnprocessable();
        $ready = $this->artifact($id);
        $this->assertFileExists($ready);
        $this->get($path.'/'.$id.'/download')->assertOk();
        $hr->roles()->detach();
        $this->getJson($path.'/'.$id.'/download')->assertForbidden();
        $this->travel(25)->hours();
        $this->artisan('certificates:clean-packages')->assertSuccessful();
        $this->assertFileDoesNotExist($ready);
        $disk->assertExists([$a->path, $b->path, $c->path]);
        $this->assertSame($original, $disk->get($b->path));
        $this->assertDatabaseCount('certificates', 3);
        $this->assertDatabaseCount('certificate_packages', 0);
        $this->assertDatabaseCount('certificate_jobs', 0);
    }

    public function test_abandoned_attempt_recovers_and_revoked_requester_is_rechecked_by_worker(): void
    {
        $hr = $this->member('HR');
        $program = $this->program();
        $record = $this->stored($program, 1);
        $this->actingAs($hr, 'api');
        $path = $this->endpoint($program).'/packages';
        $id = $this->postJson($path, $this->payload('pdf') + ['selection' => 'all'])->assertAccepted()->json('data.id');
        $job = Queue::connection('certificates')->pop('certificates');
        $packages = app(CertificatePackages::class);
        $stale = $packages->claim($id, 1);
        $stalePath = $packages->directory($id, $stale->attempt);
        $disk = Storage::disk('private');
        $disk->put('certificates/packages/'.$id.'/'.$stale->attempt.'/artifact.partial', 'incomplete');
        $this->getJson($path)->assertOk()->assertJsonPath('data.0.status', 'processing');
        $this->assertNull($packages->claim($id, 1));

        $this->travel(config('queue.connections.certificates.retry_after') + 1)->seconds();
        $this->work();
        unset($job);
        $this->assertFileExists($this->artifact($id));
        $this->assertDirectoryDoesNotExist($stalePath);
        $packages->finish($stale, 'interrupted');
        $this->assertSame('ready', CertificatePackage::findOrFail($id)->status);

        $other = $this->postJson($path, $this->payload('zip') + ['selection' => 'all'])->assertAccepted()->json('data.id');
        $hr->roles()->detach();
        $this->work();
        $this->assertDatabaseHas('certificate_packages', ['id' => $other, 'status' => 'failed', 'failure_code' => 'authorization_revoked']);
        $this->assertDatabaseCount('certificate_jobs', 0);
        Storage::disk('local')->assertExists($record->path);
    }

    private function endpoint(TrainingProgram $program): string
    {
        return '/api/v1/certificates/archive/programs/'.$program->id;
    }

    private function payload(string $format): array
    {
        return ['format' => $format, 'request_key' => (string) Str::uuid()];
    }

    private function work(): float
    {
        $start = hrtime(true);
        app('queue.worker')->runNextJob('certificates', 'certificates', new WorkerOptions(sleep: 0, maxTries: 3));

        return (hrtime(true) - $start) / 1e9;
    }

    private function artifact(string $id): string
    {
        $item = CertificatePackage::findOrFail($id);
        $this->assertSame('ready', $item->status);

        return app(CertificatePackages::class)->directory($id, $item->attempt).'/artifact.'.$item->format;
    }

    private function stored(TrainingProgram $program, int $index): Certificate
    {
        $trainee = $this->trainee($program);
        $path = 'certificates/historical-'.$trainee->id.'.pdf';
        Storage::disk('local')->put($path, $this->pdf($index));

        return Certificate::create(['trainee_id' => $trainee->id, 'training_program_id' => $program->id,
            'issue_date' => '2026-12-01', 'path' => $path]);
    }

    private function geometry(int $index): array
    {
        return match ($index % 3) {
            0 => [400, 600],
            1 => [640, 380],
            2 => [560, 340],
        };
    }

    private function pdf(int $index, bool $active = false): string
    {
        [$width, $height] = $this->geometry($index);
        $rotated = $index % 3 === 2;
        $size = $rotated ? '0 0 380 610' : "0 0 $width $height";
        $crop = $rotated ? '/CropBox [15 10 355 570] /Rotate 90' : '';
        $contents = "0 .5 .5 rg 110 160 50 40 re f\nBT /F1 19 Tf 110 140 Td (Certificate $index) Tj ET";
        $second = $index === 80;
        $pages = $second ? '/Count 2 /Kids [3 0 R 6 0 R]' : '/Count 1 /Kids [3 0 R]';
        $action = $active ? '/OpenAction << /S /JavaScript /JS (void 0) >>' : '';
        $objects = [
            "<< /Type /Catalog /Pages 2 0 R $action >>",
            "<< /Type /Pages $pages >>",
            "<< /Type /Page /Parent 2 0 R /MediaBox [$size] $crop /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
            '<< /Length '.strlen($contents)." >>\nstream\n$contents\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        if ($second) {
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 480 480] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>';
        }
        $pdf = "%PDF-1.4\n";
        $xref = "0000000000 65535 f \n";
        foreach ($objects as $offset => $object) {
            $xref .= sprintf("%010d 00000 n \n", strlen($pdf));
            $pdf .= ($offset + 1)." 0 obj\n$object\nendobj\n";
        }
        $count = count($objects) + 1;

        return $pdf."xref\n0 $count\n$xref"."trailer\n<< /Root 1 0 R /Size $count >>\nstartxref\n".strlen($pdf)."\n%%EOF\n";
    }
}
