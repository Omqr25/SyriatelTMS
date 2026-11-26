<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\TraineeStatus;
use App\Enums\TrainingProgramStatus;
use App\Enums\UserRole;
use App\Jobs\DeliverInboxPush;
use App\Models\Absence;
use App\Models\Notification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\AttendanceFixtures;
use Tests\TestCase;

class AttendanceHardeningTest extends TestCase
{
    use AttendanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
        $this->travelTo(Carbon::parse('2026-11-25 10:00:00', 'Asia/Damascus'));
        Queue::fake();
    }

    public function test_unassigned_leave_does_not_grant_access_to_a_supervisor_without_a_profile(): void
    {
        $leave = $this->attendanceLeave($this->attendanceTrainee(), ['trainer_id' => null]);

        $this->actingAs($this->attendanceUser(UserRole::TRAINER), 'api')
            ->getJson("/api/v1/vacation_request/find/{$leave->id}")->assertForbidden();
        $this->actingAs($this->attendanceUser(UserRole::TRAINING_TEAM), 'api')
            ->getJson("/api/v1/vacation_request/find/{$leave->id}")->assertOk();
    }

    public function test_absence_picker_uses_the_assigned_trainees_actual_training_period(): void
    {
        $eligible = $this->attendanceTrainee();
        $registrationOpen = $this->attendanceTrainee();
        $unassigned = $this->attendanceTrainee();
        $unassigned->application->trainingProgram->forceFill(['status' => TrainingProgramStatus::CLOSED])->save();
        $eligible->application->trainingProgram->forceFill([
            'status' => TrainingProgramStatus::CLOSED,
            'start_date' => today(),
            'end_date' => today(),
        ])->save();
        $registrationOpen->update(['trainer_id' => $eligible->trainer_id]);
        $registrationOpen->application->trainingProgram->update([
            'start_date' => today()->addDay(),
        ]);
        $url = '/api/v1/absence/eligible-trainees';

        $this->actingAs($eligible->user, 'api')->getJson($url)->assertForbidden();
        $this->actingAs($eligible->trainer->user, 'api')->getJson($url)
            ->assertOk()->assertJsonPath('data', [['id' => $eligible->id, 'name' => $eligible->user->name]]);
        $this->getJson('/api/v1/trainee/trainer')->assertOk()
            ->assertJsonPath('data.0.id', $registrationOpen->id);
        $this->postJson("/api/v1/absence/recordAbsence/{$eligible->id}")->assertOk();

        $eligible->application->update(['status' => ApplicationStatus::COMPLETED]);
        $this->getJson($url)->assertOk()->assertJsonPath('data', []);
    }

    public function test_approval_rechecks_both_program_bounds_and_allows_past_leave(): void
    {
        $trainee = $this->attendanceTrainee();
        $program = $trainee->application->trainingProgram;
        $past = $this->attendanceLeave($trainee, ['start_date' => '2026-11-24', 'end_date' => '2026-11-24']);
        $pending = $this->attendanceLeave($trainee);
        $this->actingAs($trainee->trainer->user, 'api');

        $program->update(['start_date' => '2026-11-25', 'end_date' => '2026-11-28']);
        $this->patchJson("/api/v1/vacation_request/decide/{$past->id}", ['status' => 'accepted'])
            ->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->patchJson("/api/v1/vacation_request/decide/{$pending->id}", ['status' => 'accepted'])
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->assertSame('pending', $pending->fresh()->status->value);
        $this->assertDatabaseMissing('notifications', ['event_key' => "vacation_request_decided:{$pending->id}:accepted"]);
        $this->patchJson("/api/v1/vacation_request/decide/{$pending->id}", ['status' => 'rejected'])->assertOk();

        $program->update(['start_date' => '2026-11-24']);
        $this->patchJson("/api/v1/vacation_request/decide/{$past->id}", ['status' => 'accepted'])
            ->assertOk()->assertJsonPath('data.status', 'accepted');
    }

    public function test_only_the_assigned_supervisor_can_record_once_per_day_even_after_reassignment(): void
    {
        $trainee = $this->attendanceTrainee();
        $other = $this->attendanceTrainee()->trainer;
        $url = "/api/v1/absence/recordAbsence/{$trainee->id}";

        $this->actingAs($other->user, 'api')->postJson($url)->assertForbidden();
        $this->actingAs($trainee->trainer->user, 'api')->postJson($url)->assertOk();
        $this->postJson($url)->assertStatus(400);

        $trainee->update(['trainer_id' => $other->id]);
        $this->actingAs($other->user, 'api')->postJson($url)->assertStatus(400);
        $this->assertDatabaseCount('absences', 1);
    }

    public function test_absence_respects_damascus_dates_program_boundaries_and_working_days(): void
    {
        $trainee = $this->attendanceTrainee();
        $this->actingAs($trainee->trainer->user, 'api');
        $url = "/api/v1/absence/recordAbsence/{$trainee->id}";

        foreach (['2026-11-21' => 422, '2026-11-27' => 422, '2026-11-28' => 422, '2026-12-16' => 403] as $date => $code) {
            $this->travelTo(Carbon::parse("$date 09:00", 'Asia/Damascus'));
            $this->postJson($url, [], ['Accept-Language' => 'ar'])->assertStatus($code);
        }
        $this->assertDatabaseEmpty('absences');
        $this->travelTo(Carbon::parse('2026-11-27 09:00', 'Asia/Damascus'));
        $this->postJson($url, [], ['Accept-Language' => 'ar'])->assertUnprocessable()
            ->assertJsonPath('message', 'يمكن تسجيل الغياب في أيام العمل خلال فترة التدريب فقط. الجمعة والسبت عطلة.');

        $this->travelTo(Carbon::parse('2026-11-21 21:30:00', 'UTC'));
        $this->postJson($url)->assertOk()->assertJsonPath('data.date', '22-11-2026');
        $this->travelTo(Carbon::parse('2026-12-15 10:00', 'Asia/Damascus'));
        $this->postJson($url)->assertOk()->assertJsonPath('data.date', '15-12-2026');
    }

    public function test_inactive_and_ended_enrollments_reject_attendance_and_leave_writes(): void
    {
        $trainee = $this->attendanceTrainee();
        $leave = $this->attendanceLeave($trainee);

        foreach ([
            [TraineeStatus::COMPLETED, ApplicationStatus::COMPLETED, '2026-12-15'],
            [TraineeStatus::FIRED, ApplicationStatus::FIRED, '2026-12-15'],
            [TraineeStatus::ACTIVE, ApplicationStatus::ACTIVE, '2026-11-24'],
        ] as [$traineeStatus, $applicationStatus, $programEnd]) {
            $trainee->update(['status' => $traineeStatus]);
            $trainee->application->update(['status' => $applicationStatus]);
            $trainee->application->trainingProgram->update(['end_date' => $programEnd]);

            $this->actingAs($trainee->trainer->user, 'api')
                ->postJson("/api/v1/absence/recordAbsence/{$trainee->id}")->assertForbidden();
            $this->patchJson("/api/v1/vacation_request/decide/{$leave->id}", ['status' => 'accepted'])->assertForbidden();
            $this->actingAs($trainee->user, 'api')->getJson('/api/v1/vacation_request/listForTrainee')
                ->assertOk()->assertJsonPath('data.0.can_edit', false)->assertJsonPath('extraData.can_request', false);
            $this->putJson("/api/v1/vacation_request/update/{$leave->id}", ['reason' => 'Change'])->assertForbidden();
            $this->deleteJson("/api/v1/vacation_request/{$leave->id}")->assertForbidden();
            $this->postJson('/api/v1/vacation_request', [
                'start_date' => '2026-11-27', 'end_date' => '2026-11-30', 'reason' => 'Travel',
            ])->assertForbidden();
        }
        $this->assertDatabaseEmpty('notifications');
    }

    public function test_leave_creation_and_partial_updates_validate_the_resulting_range_and_allow_single_day(): void
    {
        $trainee = $this->attendanceTrainee();
        $this->actingAs($trainee->user, 'api');
        $data = ['start_date' => '2026-11-26', 'end_date' => '2026-11-26', 'reason' => 'Appointment'];
        $url = '/api/v1/vacation_request';

        $this->postJson($url, ['start_date' => '2026-11-25'] + $data)->assertJsonValidationErrors('start_date');
        $this->postJson($url, ['end_date' => '2026-12-16'] + $data)->assertJsonValidationErrors('end_date');
        $this->postJson($url, ['end_date' => '2026-11-25'] + $data)->assertJsonValidationErrors('end_date');
        $this->postJson($url, ['start_date' => '2026-11-26T00:00:00+00:00'] + $data)->assertJsonValidationErrors('start_date');
        $leaveId = $this->postJson($url, $data)->assertOk()
            ->assertJsonPath('data.start_date', '2026-11-26')
            ->assertJsonPath('data.submitted_at', '2026-11-25T10:00:00+03:00')
            ->assertJsonPath('data.can_edit', true)->json('data.id');

        $createdNotice = Notification::where('event_key', "vacation_request_created:{$leaveId}")->firstOrFail();
        $this->assertSame($trainee->trainer->user_id, $createdNotice->notified()->sole()->notified_id);

        $update = "{$url}/update/{$leaveId}";
        $this->putJson($update, ['start_date' => '2026-11-27'])->assertJsonValidationErrors('end_date');
        $this->putJson($update, ['end_date' => '2026-11-25'])->assertJsonValidationErrors('end_date');
        $this->putJson($update, ['end_date' => '2026-12-16'])->assertJsonValidationErrors('end_date');
        $this->putJson($update, ['end_date' => '2026-11-27'])->assertOk();
        $this->putJson($update, ['start_date' => '2026-11-27'])->assertOk()
            ->assertJsonPath('data.start_date', '2026-11-27')->assertJsonPath('data.end_date', '2026-11-27');
        $this->putJson($update, ['reason' => 'New reason'])->assertOk()->assertJsonPath('data.reason', 'New reason');

        $trainee->application->trainingProgram->update(['end_date' => '2026-11-26']);
        $this->putJson($update, ['reason' => 'Date moved'])->assertJsonValidationErrors('end_date');
        $this->deleteJson("{$url}/{$leaveId}")->assertOk();
        $this->assertDatabaseMissing('vacation_requests', ['id' => $leaveId]);
    }

    public function test_owner_edit_and_cancellation_cutoff_is_damascus_midnight(): void
    {
        $trainee = $this->attendanceTrainee();
        $leave = $this->attendanceLeave($trainee, ['start_date' => '2026-11-26 12:30:00']);
        $this->actingAs($trainee->user, 'api');
        $this->travelTo(Carbon::parse('2026-11-25 20:59:00', 'UTC'));
        $this->getJson('/api/v1/vacation_request/listForTrainee')->assertOk()
            ->assertJsonPath('extraData.earliest_leave_date', '2026-11-26')->assertJsonPath('data.0.can_cancel', true);

        $this->travelTo(Carbon::parse('2026-11-25 21:00:00', 'UTC'));
        $this->getJson('/api/v1/vacation_request/listForTrainee')->assertOk()
            ->assertJsonPath('extraData.earliest_leave_date', '2026-11-27')
            ->assertJsonPath('data.0.can_cancel', false)->assertJsonPath('data.0.can_edit', false);
        $this->putJson("/api/v1/vacation_request/update/{$leave->id}", ['reason' => 'Change'])
            ->assertForbidden();
        $this->deleteJson("/api/v1/vacation_request/{$leave->id}")->assertForbidden();
    }

    public function test_only_terminal_decisions_generate_one_matching_inbox_event_and_outbox_entry(): void
    {
        $trainee = $this->attendanceTrainee();
        $this->actingAs($trainee->trainer->user, 'api');
        foreach (['accepted' => 'Approved', 'rejected' => 'Rejected'] as $status => $label) {
            $leave = $this->attendanceLeave($trainee, ['start_date' => '2026-11-24']);
            $url = "/api/v1/vacation_request/decide/{$leave->id}";
            foreach (['pending', 'cancelled', 'invalid'] as $invalid) {
                $this->patchJson($url, ['status' => $invalid])->assertJsonValidationErrors('status');
            }
            $event = "vacation_request_decided:{$leave->id}:{$status}";
            $this->assertDatabaseMissing('notifications', ['event_key' => $event]);

            $this->patchJson($url, ['status' => $status])->assertOk()
                ->assertJsonPath('data.status', $status)->assertJsonPath('data.can_decide', false);
            $this->patchJson($url, ['status' => $status === 'accepted' ? 'rejected' : 'accepted'])->assertForbidden();

            $notification = Notification::where('event_key', $event)->sole();
            $this->assertSame("Vacation Request {$label}", $notification->title);
            $this->assertSame($status, $notification->metadata['status']);
            $recipient = $notification->notified()->sole();
            $this->assertSame($trainee->user_id, $recipient->notified_id);
            $this->assertDatabaseHas('notification_deliveries', ['notified_id' => $recipient->id]);
        }
        $this->assertDatabaseCount('notification_deliveries', 2);
        Queue::assertPushed(DeliverInboxPush::class, 2);
    }

    public function test_legacy_non_terminal_event_does_not_suppress_the_valid_decision(): void
    {
        $trainee = $this->attendanceTrainee();
        $leave = $this->attendanceLeave($trainee);
        $legacy = Notification::create([
            'event_key' => "vacation_request_decided:{$leave->id}",
            'type' => 'vacation_request_decided',
            'title' => 'Vacation Request Rejected',
            'description' => 'Legacy incorrect decision',
            'notifier_id' => $trainee->trainer->user_id,
            'metadata' => ['vacation_request_id' => $leave->id, 'status' => 'pending'],
        ]);
        $legacy->notified()->create(['notified_id' => $trainee->user_id]);

        $this->actingAs($trainee->trainer->user, 'api')
            ->patchJson("/api/v1/vacation_request/decide/{$leave->id}", ['status' => 'accepted'])->assertOk();

        $decision = Notification::where('event_key', "vacation_request_decided:{$leave->id}:accepted")->sole();
        $this->assertSame('accepted', $decision->metadata['status']);
        $this->assertSame('pending', $legacy->fresh()->metadata['status']);
        $recipient = $decision->notified()->sole();
        $this->assertSame($trainee->user_id, $recipient->notified_id);
        $this->assertDatabaseHas('notification_deliveries', ['notified_id' => $recipient->id]);
    }

    public function test_absence_keeps_existing_approved_leave_association(): void
    {
        $trainee = $this->attendanceTrainee();
        $leave = $this->attendanceLeave($trainee, ['status' => 'accepted', 'start_date' => '2026-11-25 12:00:00']);
        $this->actingAs($trainee->trainer->user, 'api')->postJson("/api/v1/absence/recordAbsence/{$trainee->id}")
            ->assertOk()->assertJsonPath('data.status', 'absent');
        $this->assertDatabaseHas('absences', ['request_id' => $leave->id, 'trainee_id' => $trainee->id]);
    }

    public function test_role_scoped_lists_are_paginated_stably_without_per_row_queries(): void
    {
        $trainee = $this->attendanceTrainee();
        $other = $this->attendanceTrainee();
        $other->update(['trainer_id' => $trainee->trainer_id]);

        $absenceIds = [];
        $leaveIds = [];
        for ($day = 0; $day < 28; $day++) {
            $subject = $day % 2 ? $trainee : $other;
            $leaveIds[] = $this->attendanceLeave($subject, ['created_at' => now()])->id;
            $absenceIds[] = Absence::factory()->create([
                'trainee_id' => $subject->id, 'trainer_id' => $subject->trainer_id, 'date' => today()->subDays($day),
            ])->id;
        }

        $supervisor = $trainee->trainer->user;
        $this->actingAs($supervisor, 'api');
        foreach ([
            '/api/v1/absence/findAll' => $absenceIds[0],
            '/api/v1/vacation_request/listForTrainer' => end($leaveIds),
        ] as $url => $firstId) {
            DB::enableQueryLog();
            $first = $this->getJson($url)->assertOk()->assertJsonCount(25, 'data')
                ->assertJsonPath('meta.total', 28)->assertJsonPath('meta.last_page', 2)
                ->assertJsonPath('data.0.id', $firstId);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();
            DB::flushQueryLog();
            $this->assertLessThan(15, $queries);
            $second = $this->getJson($url.'?page=2')->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('meta.current_page', 2);
            $this->assertEmpty(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
        }

        $this->actingAs($trainee->user, 'api')->getJson('/api/v1/absence/findAll')->assertJsonPath('meta.total', 14);
        $this->getJson('/api/v1/vacation_request/listForTrainee')->assertJsonPath('meta.total', 14);
        $this->actingAs($this->attendanceUser(UserRole::TRAINING_TEAM), 'api')->getJson('/api/v1/absence/findAll')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.date', '25-11-2026');
        $this->getJson('/api/v1/absence/findAll?name=NoSuchName')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/absence/findAll?date=2026-11-24')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_retained_profiles_do_not_grant_absence_history_after_role_revocation(): void
    {
        $trainee = $this->attendanceTrainee();
        $absence = Absence::factory()->create([
            'trainee_id' => $trainee->id,
            'trainer_id' => $trainee->trainer_id,
            'date' => today(),
            'reason' => 'Private absence reason',
        ]);

        foreach ([$trainee->trainer->user, $trainee->user] as $user) {
            $this->actingAs($user, 'api');
            $this->getJson('/api/v1/absence/findAll')->assertOk()
                ->assertJsonPath('data.0.id', $absence->id)
                ->assertJsonPath('data.0.reason', 'Private absence reason');

            $user->roles()->detach();

            $this->getJson('/api/v1/absence/findAll')->assertOk()
                ->assertJsonPath('data', [])
                ->assertJsonPath('meta.total', 0)
                ->assertJsonMissing(['reason' => 'Private absence reason']);
        }
    }

    public function test_daily_unique_migration_preserves_legacy_records_without_permitting_new_duplicates(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('The migration replay uses transactional SQLite DDL.');
        }
        $trainee = $this->attendanceTrainee();
        $migration = require database_path('migrations/2026_11_25_000001_enforce_unique_daily_absences.php');
        $migration->down();
        $attributes = [
            'date' => '2026-11-25', 'trainee_id' => $trainee->id, 'trainer_id' => $trainee->trainer_id,
        ];

        $first = Absence::factory()->create($attributes + ['reason' => 'First entry']);
        Absence::factory()->create($attributes + ['reason' => 'Historical duplicate']);
        Absence::factory()->create($attributes + ['reason' => 'Second historical duplicate']);
        $migration->up();

        $this->assertDatabaseCount('absences', 3);
        $attributes['date'] = $first->getRawOriginal('date');
        $this->assertDatabaseHas('absences', $attributes + ['reason' => 'First entry', 'is_canonical' => true]);
        $this->assertDatabaseHas('absences', $attributes + ['reason' => 'Historical duplicate', 'is_canonical' => null]);
        $this->assertDatabaseHas('absences', $attributes + ['reason' => 'Second historical duplicate', 'is_canonical' => null]);
        $this->expectException(UniqueConstraintViolationException::class);
        Absence::factory()->create($attributes);
    }
}
