<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CertificateArchiveMigrationTest extends TestCase
{
    private const LEGACY = 'database/migrations/2026_09_28_122422_create_certificates_table.php';

    private const GUARD = 'database/migrations/2026_11_27_000001_protect_certificate_archive.php';

    private const FOUNDATION = 'database/migrations/2026_11_27_000002_create_certificate_foundation.php';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.certificate_fixture' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('certificate_fixture');

        foreach (['users', 'trainees', 'training_programs'] as $table) {
            Schema::create($table, fn ($schema) => $schema->id());
            DB::table($table)->insert(['id' => 1]);
        }
        (require base_path(self::LEGACY))->up();
        DB::table('certificates')->insert($this->legacy());
    }

    private function legacy(): array
    {
        return ['trainee_id' => 1, 'training_program_id' => 1, 'issue_date' => '2026-10-02',
            'path' => 'certificates/existing.pdf', 'created_at' => '2026-10-02 01:02:03'];
    }

    public function test_legacy_bytes_and_fields_survive_and_parent_delete_is_restricted(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('certificates/existing.pdf', 'untouched legacy bytes');
        (require base_path(self::GUARD))->up();
        (require base_path(self::FOUNDATION))->up();
        $certificate = DB::table('certificates')->first();
        $this->assertEquals(1, $certificate->id);
        foreach ($this->legacy() as $key => $value) {
            $this->assertEquals($value, $certificate->{$key});
        }
        $this->assertNull($certificate->snapshot);
        $this->assertNull($certificate->number);
        $this->assertSame('issued', $certificate->state);
        $this->assertSame('untouched legacy bytes', Storage::disk('local')->get($certificate->path));
        $this->expectException(QueryException::class);
        DB::table('trainees')->where('id', 1)->delete();
    }

    public function test_preflight_reports_duplicate_identity_and_leaves_both_records_untouched(): void
    {
        DB::table('certificates')->insert($this->legacy());
        try {
            (require base_path(self::GUARD))->up();
            $this->fail('Historical conflicts must stop the migration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('"copies":2', $exception->getMessage());
            $this->assertSame(2, DB::table('certificates')->count());
            $this->assertFalse(Schema::hasColumn('certificates', 'state'));
        }
    }
}
