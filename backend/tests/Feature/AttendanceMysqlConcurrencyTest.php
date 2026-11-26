<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\Notification;
use App\Models\Trainee;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\AttendanceFixtures;
use Tests\TestCase;

// Run with RUN_MYSQL_ATTENDANCE_CONCURRENCY=1 against a disposable, migrated MySQL 8 test database.
// The account must be able to read performance_schema lock-wait data.
class AttendanceMysqlConcurrencyTest extends TestCase
{
    use AttendanceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        if (! env('RUN_MYSQL_ATTENDANCE_CONCURRENCY')) {
            $this->markTestSkipped('Requires an explicitly enabled, disposable MySQL test database.');
        }
        $this->assertSame('mysql', DB::getDriverName());
        $this->assertStringEndsWith('_test', DB::getDatabaseName());
    }

    public function test_two_concurrent_absence_requests_create_one_record(): void
    {
        $trainee = $this->attendanceTrainee();
        $request = [$trainee->trainer->user_id, 'POST', "/api/v1/absence/recordAbsence/{$trainee->id}", ['reason' => 'Absent today']];

        $this->assertSame([200, 400], $this->race($trainee, $request, $request));
        $this->assertSame(1, Absence::where('trainee_id', $trainee->id)->count());
    }

    public function test_conflicting_decisions_create_one_matching_notification_and_outbox_entry(): void
    {
        $trainee = $this->attendanceTrainee();
        $leave = $this->attendanceLeave($trainee);
        $url = "/api/v1/vacation_request/decide/{$leave->id}";

        $this->assertSame([200, 403], $this->race($trainee,
            [$trainee->trainer->user_id, 'PATCH', $url, ['status' => 'accepted']],
            [$trainee->trainer->user_id, 'PATCH', $url, ['status' => 'rejected']],
        ));
        $notification = Notification::where('type', 'vacation_request_decided')->where('metadata->vacation_request_id', $leave->id)->sole();
        $status = $leave->fresh()->status->value;
        $this->assertSame($status, $notification->metadata['status']);
        $this->assertStringEndsWith($status === 'accepted' ? 'Approved' : 'Rejected', $notification->title);
        $recipient = $notification->notified()->sole();
        $this->assertSame($trainee->user_id, $recipient->notified_id);
        $this->assertSame(1, DB::table('notification_deliveries')->where('notified_id', $recipient->id)->count());
    }

    public function test_cancellation_and_approval_cannot_both_succeed(): void
    {
        $trainee = $this->attendanceTrainee();
        $leave = $this->attendanceLeave($trainee);
        $event = "vacation_request_decided:{$leave->id}:accepted";
        $results = $this->race($trainee,
            [$trainee->user_id, 'DELETE', "/api/v1/vacation_request/{$leave->id}", []],
            [$trainee->trainer->user_id, 'PATCH', "/api/v1/vacation_request/decide/{$leave->id}", ['status' => 'accepted']],
        );

        $this->assertSame([200, 403], $results);
        $this->assertContains($leave->fresh()->status->value, ['accepted', 'cancelled']);
        if ($leave->fresh()->status->value === 'accepted') {
            $this->assertDatabaseHas('notifications', ['event_key' => $event]);
        } else {
            $this->assertDatabaseMissing('notifications', ['event_key' => $event]);
        }
    }

    public function test_conflicting_partial_edits_validate_against_the_current_locked_range(): void
    {
        $trainee = $this->attendanceTrainee();
        $leave = $this->attendanceLeave($trainee);
        $url = "/api/v1/vacation_request/update/{$leave->id}";

        $this->assertSame([200, 422], $this->race($trainee,
            [$trainee->user_id, 'PUT', $url, ['start_date' => '2026-11-29']],
            [$trainee->user_id, 'PUT', $url, ['end_date' => '2026-11-28']],
        ));
        $leave->refresh();
        $this->assertTrue($leave->start_date->lte($leave->end_date));
    }

    public function test_edit_cannot_overwrite_a_rejection(): void
    {
        $trainee = $this->attendanceTrainee();
        $leave = $this->attendanceLeave($trainee, ['reason' => 'Original reason']);
        $results = $this->race($trainee,
            [$trainee->user_id, 'PUT', "/api/v1/vacation_request/update/{$leave->id}", ['reason' => 'Updated reason']],
            [$trainee->trainer->user_id, 'PATCH', "/api/v1/vacation_request/decide/{$leave->id}", ['status' => 'rejected']],
        );

        $this->assertContains($results, [[200, 200], [200, 403]]);
        $leave->refresh();
        $this->assertSame('rejected', $leave->status->value);
        $this->assertSame($results === [200, 200] ? 'Updated reason' : 'Original reason', $leave->reason);
    }

    public function test_migration_preserves_legacy_mysql_absence_duplicates(): void
    {
        $trainee = $this->attendanceTrainee();
        $migration = require database_path('migrations/2026_11_25_000001_enforce_unique_daily_absences.php');
        $migration->down();
        $attributes = ['date' => '2026-11-25', 'trainee_id' => $trainee->id, 'trainer_id' => $trainee->trainer_id];
        try {
            Absence::factory()->create($attributes + ['reason' => 'Original reason']);
            Absence::factory()->create($attributes + ['reason' => 'Historical duplicate']);
            Absence::factory()->create($attributes + ['reason' => 'Another historical duplicate']);
        } finally {
            $migration->up();
        }

        $this->assertSame(3, Absence::where('trainee_id', $trainee->id)->count());
        $this->assertDatabaseHas('absences', $attributes + ['reason' => 'Original reason', 'is_canonical' => true]);
        $this->assertDatabaseHas('absences', $attributes + ['reason' => 'Historical duplicate', 'is_canonical' => null]);
        $this->assertDatabaseHas('absences', $attributes + ['reason' => 'Another historical duplicate', 'is_canonical' => null]);
        $this->expectException(UniqueConstraintViolationException::class);
        Absence::factory()->create($attributes);
    }

    public function test_approval_waits_for_reschedule_and_validates_the_committed_program_dates(): void
    {
        $trainee = $this->attendanceTrainee();
        $program = $trainee->application->trainingProgram;
        $leave = $this->attendanceLeave($trainee);

        $this->assertSame([422], $this->raceWithLock('training_programs', function () use ($program) {
            $program->newQuery()->whereKey($program->id)->lockForUpdate()->firstOrFail()
                ->update(['end_date' => '2026-11-28']);
        }, [$trainee->trainer->user_id, 'PATCH', "/api/v1/vacation_request/decide/{$leave->id}", ['status' => 'accepted']]));

        $this->assertSame('pending', $leave->fresh()->status->value);
        $this->assertDatabaseMissing('notifications', ['event_key' => "vacation_request_decided:{$leave->id}:accepted"]);
    }

    public function test_dismissal_blocks_waiting_attendance_and_leave_writes(): void
    {
        $trainee = $this->attendanceTrainee();
        $leave = $this->attendanceLeave($trainee);
        $hr = $this->attendanceUser(\App\Enums\UserRole::TRAINING_TEAM);

        $this->assertSame([403, 403], $this->raceWithLock('trainees', function () use ($trainee, $hr) {
            app(\App\Services\Organization\TraineeService::class)
                ->fireTrainee($trainee->id, ['reason' => 'Test dismissal'], $hr);
        },
            [$trainee->trainer->user_id, 'POST', "/api/v1/absence/recordAbsence/{$trainee->id}", ['reason' => 'Absent today']],
            [$trainee->trainer->user_id, 'PATCH', "/api/v1/vacation_request/decide/{$leave->id}", ['status' => 'accepted']],
        ));

        $this->assertSame('pending', $leave->fresh()->status->value);
        $this->assertDatabaseMissing('absences', ['trainee_id' => $trainee->id]);
    }

    public function test_concurrent_overlapping_requests_create_one_pending_leave(): void
    {
        $trainee = $this->attendanceTrainee();
        $path = '/api/v1/vacation_request';
        $this->assertSame([200, 422], $this->race($trainee,
            [$trainee->user_id, 'POST', $path, ['start_date' => '2026-11-25', 'end_date' => '2026-11-27', 'reason' => 'First']],
            [$trainee->user_id, 'POST', $path, ['start_date' => '2026-11-27', 'end_date' => '2026-11-29', 'reason' => 'Second']],
        ));
        $this->assertSame(1, $trainee->vacationRequests()->count());
    }

    private function race(Trainee $trainee, array ...$requests): array
    {
        return $this->raceWithLock('trainees', fn () => Trainee::lockForUpdate()->findOrFail($trainee->id), ...$requests);
    }

    private function raceWithLock(string $table, \Closure $lock, array ...$requests): array
    {
        $workers = [];
        try {
            DB::beginTransaction();
            $lock();
            foreach ($requests as [$user, $method, $uri, $body]) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/attendance-request.php'),
                    $user, $method, $uri, json_encode($body, JSON_THROW_ON_ERROR)], base_path(), timeout: 20);
                $workers[] = $worker;
                $worker->start();
            }

            $deadline = microtime(true) + 10;
            do {
                foreach ($workers as $worker) {
                    if (! $worker->isRunning()) {
                        $this->fail('Worker exited while waiting for locks: '.$worker->getErrorOutput().$worker->getOutput());
                    }
                }
                $waiters = (int) DB::selectOne(
                    'SELECT COUNT(DISTINCT waits.REQUESTING_THREAD_ID) AS total
                     FROM performance_schema.data_lock_waits waits
                     JOIN performance_schema.data_locks locks ON locks.ENGINE_LOCK_ID = waits.REQUESTING_ENGINE_LOCK_ID
                     WHERE locks.OBJECT_SCHEMA = ? AND locks.OBJECT_NAME = ?',
                    [DB::getDatabaseName(), $table],
                )->total;
                if ($waiters === count($requests)) {
                    break;
                }
                usleep(25_000);
            } while (microtime(true) < $deadline);

            $this->assertSame(count($requests), $waiters, 'All workers must reach the database lock before release.');
            DB::commit();

            $statuses = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $response = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertLessThan(500, $response['status'], $worker->getOutput());
                $statuses[] = $response['status'];
            }
            sort($statuses);

            return $statuses;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                $worker->stop();
            }
        }
    }
}
