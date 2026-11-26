<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Role;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrainingProgramMigrationTest extends TestCase
{
    private const API = '/api/v1/training_program';

    private const FIELDS = [
        'name' => 'Winter Training', 'application_start_date' => '2030-01-10', 'application_end_date' => '2030-01-20',
        'start_date' => '2030-02-01', 'end_date' => '2030-02-28',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // Replay only the migration under test, outside a transaction that MySQL DDL would commit.
        $this->artisan('migrate:fresh')->assertSuccessful();
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_migration_classifies_existing_evidence_and_leaves_uncertain_future_programs_protected(): void
    {
        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
        $this->travelTo(Carbon::parse('2030-01-01'));
        $migration = require database_path('migrations/2026_11_18_000004_add_training_program_management.php');
        $migration->down();
        $legacy = fn ($overrides) => DB::table('training_programs')->insertGetId(array_replace(
            self::FIELDS + ['description' => '', 'status' => 'Closed'], $overrides
        ));
        $past = $legacy(['application_start_date' => '2029-12-31']);
        $previouslyOpen = $legacy(['status' => 'Open']);
        $linked = $legacy([]);
        Application::factory()->create(['training_program_id' => $linked]);
        $unknown = $legacy([]);
        $migration->up();
        foreach ([$past, $previouslyOpen, $linked] as $id) {
            $this->assertTrue(TrainingProgram::findOrFail($id)->registration_has_opened);
        }
        $this->assertNull(TrainingProgram::findOrFail($unknown)->registration_has_opened);
        $hr = User::factory()->create();
        $hr->roles()->attach(Role::firstOrCreate(['role' => 'HR']));
        $this->actingAs($hr, 'api');
        $this->deleteJson(self::API.'/'.$unknown)->assertConflict()->assertJsonPath('data.reason', 'unknown_history');
    }
}
