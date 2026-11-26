<?php

namespace Tests\Feature;

use App\Models\Absence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\AttendanceFixtures;
use Tests\Support\IsolatedTestDatabase;
use Tests\TestCase;

class AttendanceRollbackTest extends TestCase
{
    use AttendanceFixtures, RefreshDatabase;

    public function test_rollback_refuses_expired_history_without_changing_schema_or_data(): void
    {
        $leave = $this->attendanceLeave($this->attendanceTrainee(), ['status' => 'expired']);

        $this->assertRollbackBlocked('expired leave records');
        $this->assertSame('expired', $leave->fresh()->status->value);
    }

    public function test_rollback_refuses_soft_deleted_history_without_restoring_it(): void
    {
        $trainee = $this->attendanceTrainee();
        $absence = Absence::factory()->create(['trainee_id' => $trainee->id, 'trainer_id' => $trainee->trainer_id]);
        $absence->delete();

        $this->assertRollbackBlocked('soft-deleted absences');
        $this->assertSoftDeleted($absence);
        $this->assertSame(0, $trainee->absences()->count());
    }

    private function assertRollbackBlocked(string $message): void
    {
        IsolatedTestDatabase::verify($this->app);
        $migration = require database_path('migrations/2026_11_26_000001_extend_absences_and_index_leave_periods.php');

        try {
            $migration->down();
            $this->fail('Rollback must refuse to remove the compatibility schema.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('absences', 'deleted_at'));
        $this->assertTrue(Schema::hasIndex('vacation_requests', 'leave_status_trainee_period'));
    }
}
