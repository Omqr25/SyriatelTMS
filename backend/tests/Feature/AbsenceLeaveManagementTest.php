<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\TraineeStatus;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Support\AttendanceFixtures;
use Tests\TestCase;

class AbsenceLeaveManagementTest extends TestCase
{
    use AttendanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
        $this->travelTo(Carbon::parse('2026-11-25 10:00:00', 'Asia/Damascus'));
        Queue::fake();
    }

    public function test_absence_requires_a_reason_and_only_the_current_supervisor_can_edit_or_delete_historical_records(): void
    {
        $trainee = $this->attendanceTrainee();
        $other = $this->attendanceTrainee();
        $hr = $this->attendanceUser(UserRole::TRAINING_TEAM);
        $create = "/api/v1/absence/recordAbsence/{$trainee->id}";
        $this->actingAs($trainee->trainer->user, 'api');
        $this->postJson($create, ['reason' => '   '])->assertJsonValidationErrors('reason');
        foreach (['2026-11-24', '2026-11-26'] as $date) {
            $this->postJson($create, ['reason' => 'No show', 'date' => $date])->assertJsonValidationErrors('date');
        }

        $id = $this->postJson($create, ['reason' => 'No show'])->assertOk()
            ->assertJsonPath('data.date', '25-11-2026')->json('data.id');
        $path = "/api/v1/absence/{$id}";
        $this->travel(1)->days();
        $this->patchJson($path, ['reason' => 'Wrong subject', 'trainee_id' => $other->id])
            ->assertJsonValidationErrors('trainee_id');
        $this->patchJson($path, ['reason' => 'Wrong day', 'date' => '2026-11-26'])->assertJsonValidationErrors('date');

        foreach ([$other->trainer->user, $trainee->user, $hr] as $user) {
            $this->actingAs($user, 'api')->patchJson($path, ['reason' => 'Unauthorized'])->assertForbidden();
            $this->deleteJson($path)->assertForbidden();
            $this->postJson($create, ['reason' => 'Unauthorized'])->assertForbidden();
        }

        $originalTrainer = $trainee->trainer_id;
        $trainee->update(['trainer_id' => $other->trainer_id, 'status' => TraineeStatus::COMPLETED]);
        $this->actingAs($trainee->trainer->user, 'api')->patchJson($path, ['reason' => 'Former supervisor'])->assertForbidden();
        $this->actingAs($other->trainer->user, 'api')->patchJson($path, ['reason' => 'Corrected reason'])
            ->assertOk()->assertJsonPath('data.date', '25-11-2026')->assertJsonPath('data.trainee_id', $trainee->id);
        $this->assertDatabaseHas('absences', ['id' => $id, 'trainer_id' => $originalTrainer, 'reason' => 'Corrected reason']);
        $this->deleteJson($path)->assertOk();
        $this->assertSoftDeleted('absences', ['id' => $id]);
        $this->getJson('/api/v1/attendance/absences')->assertOk()->assertJsonPath('data', []);
    }

    public function test_leave_approval_and_cancellation_reclassify_existing_absences_and_summary_counts(): void
    {
        $trainee = $this->attendanceTrainee();
        $hr = $this->attendanceUser(UserRole::TRAINING_TEAM);
        $this->actingAs($trainee->user, 'api');
        $leave = $this->postJson('/api/v1/vacation_request', [
            'reason' => 'Appointment', 'start_date' => '2026-11-25', 'end_date' => '2026-11-26',
        ])->assertOk()->json('data.id');
        $this->postJson('/api/v1/vacation_request', [
            'reason' => 'Overlap', 'start_date' => '2026-11-26', 'end_date' => '2026-11-27',
        ])->assertJsonValidationErrors('start_date');
        $this->assertDatabaseEmpty('absences');

        $this->actingAs($trainee->trainer->user, 'api')
            ->postJson("/api/v1/absence/recordAbsence/{$trainee->id}", ['reason' => 'Absent'])
            ->assertOk()->assertJsonPath('data.classification', 'unexcused');
        $this->patchJson("/api/v1/vacation_request/decide/{$leave}", ['status' => 'accepted'])
            ->assertOk()->assertJsonPath('data.can_cancel', true)->assertJsonPath('data.can_edit', false);
        $this->assertSame(1, Absence::count());
        $this->getJson('/api/v1/attendance/absences?classification=excused&sort=is_excused')
            ->assertOk()->assertJsonPath('data.0.classification', 'excused');
        $this->getJson("/api/v1/attendance/trainees/{$trainee->id}")->assertOk()->assertJsonPath('data.excused_absences', 1);

        $this->travel(1)->days();
        $this->postJson("/api/v1/absence/recordAbsence/{$trainee->id}", ['reason' => 'Absent again'])
            ->assertOk()->assertJsonPath('data.classification', 'excused');
        $this->actingAs($trainee->user, 'api')->deleteJson("/api/v1/vacation_request/{$leave}")->assertForbidden();
        $this->putJson("/api/v1/vacation_request/update/{$leave}", ['reason' => 'Edit approved'])->assertForbidden();
        $this->getJson('/api/v1/attendance/absences')->assertOk()->assertJsonPath('extraData.counts', [
            'total_absences' => 2, 'excused_absences' => 2, 'unexcused_absences' => 0,
        ]);
        $this->actingAs($hr, 'api')->getJson('/api/v1/attendance/leaves')->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson("/api/v1/vacation_request/{$leave}/cancel-approved")->assertForbidden();

        // Existing data can contain overlapping approved leaves from before V1.
        $this->attendanceLeave($trainee, ['status' => 'accepted', 'start_date' => '2026-11-25', 'end_date' => '2026-11-25']);
        $this->actingAs($trainee->trainer->user, 'api')
            ->patchJson("/api/v1/vacation_request/{$leave}/cancel-approved")->assertOk()
            ->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.can_cancel', false);
        $this->patchJson("/api/v1/vacation_request/{$leave}/cancel-approved")->assertForbidden();
        $this->assertDatabaseHas('vacation_requests', ['id' => $leave, 'status' => 'cancelled']);
        $event = Notification::where('event_key', "vacation_request_decided:{$leave}:cancelled")->sole();
        $this->assertSame($trainee->user_id, $event->notified()->sole()->notified_id);
        $this->assertSame('cancelled', $event->metadata['status']);

        $this->actingAs($trainee->user, 'api')->getJson('/api/v1/attendance/absences')->assertOk()
            ->assertJsonPath('data.0.classification', 'unexcused')->assertJsonPath('data.1.classification', 'excused')
            ->assertJsonPath('extraData.counts', ['total_absences' => 2, 'excused_absences' => 1, 'unexcused_absences' => 1]);
        $this->actingAs($hr, 'api')->getJson('/api/v1/attendance/leaves')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/vacation_request/find/{$leave}")->assertNotFound();
    }

    public function test_program_completion_expires_pending_requests_even_if_no_active_trainee_remains(): void
    {
        $trainee = $this->attendanceTrainee();
        $leave = $this->attendanceLeave($trainee);
        $other = [];
        foreach (['accepted', 'rejected', 'cancelled'] as $status) {
            $other[$status] = $this->attendanceLeave($trainee, ['status' => $status]);
        }

        $this->travelTo(Carbon::parse('2026-12-16 00:01:00', 'Asia/Damascus'));
        $this->actingAs($trainee->user, 'api')->deleteJson("/api/v1/vacation_request/{$leave->id}")->assertForbidden();
        $this->actingAs($trainee->trainer->user, 'api')
            ->patchJson("/api/v1/vacation_request/decide/{$leave->id}", ['status' => 'rejected'])->assertForbidden();
        $this->patchJson("/api/v1/vacation_request/{$other['accepted']->id}/cancel-approved")->assertForbidden();
        $trainee->update(['status' => TraineeStatus::COMPLETED]);
        $trainee->application->update(['status' => ApplicationStatus::COMPLETED]);

        $this->artisan('training-programs:update-status')->assertSuccessful();
        $this->assertSame('expired', $leave->fresh()->status->value);
        foreach ($other as $status => $request) {
            $this->assertSame($status, $request->fresh()->status->value);
        }
        $this->actingAs($trainee->user, 'api')->getJson('/api/v1/vacation_request/listForTrainee?status=expired')
            ->assertOk()->assertJsonPath('data.0.status', 'expired')->assertJsonPath('data.0.can_cancel', false);
        $this->actingAs($this->attendanceUser(UserRole::TRAINING_TEAM), 'api')
            ->getJson("/api/v1/vacation_request/find/{$leave->id}")->assertNotFound();
    }

    public function test_records_and_filters_respect_roles_and_include_active_trainees_with_zero_absences(): void
    {
        $unexcused = $this->attendanceTrainee();
        $zero = $this->attendanceTrainee();
        $other = $this->attendanceTrainee();
        $zero->update(['trainer_id' => $unexcused->trainer_id]);
        Absence::factory()->create(['trainee_id' => $unexcused->id, 'trainer_id' => $unexcused->trainer_id]);
        foreach (['pending', 'accepted', 'rejected', 'cancelled', 'expired'] as $status) {
            $this->attendanceLeave($zero, ['status' => $status]);
        }
        $record = "/api/v1/attendance/trainees/{$other->id}";
        $this->getJson($record)->assertUnauthorized();
        $this->actingAs($unexcused->trainer->user, 'api')->getJson($record)->assertNotFound();
        $this->getJson('/api/v1/attendance/trainees?sort=total_absences&direction=desc')
            ->assertOk()->assertJsonPath('data.0.id', $unexcused->id)->assertJsonPath('data.1.total_absences', 0);
        $this->getJson('/api/v1/attendance/trainees?recorded=without')
            ->assertOk()->assertJsonPath('data.0.id', $zero->id)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/attendance/trainees?recorded=unexcused')
            ->assertOk()->assertJsonPath('data.0.id', $unexcused->id)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/attendance/options')->assertOk()->assertJsonCount(2, 'data.programs')
            ->assertJsonPath('data.supervisors', []);
        $this->getJson('/api/v1/vacation_request/listForTrainer?trainee_id='.$zero->id)->assertOk()->assertJsonCount(5, 'data');

        $this->actingAs($zero->user, 'api')->getJson($record)->assertForbidden();
        $this->getJson('/api/v1/attendance/absences?trainee_id='.$unexcused->id)->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('extraData.counts.total_absences', 0);
        $this->actingAs($this->attendanceUser(UserRole::TRAINING_TEAM), 'api')->getJson($record)->assertOk();
        $this->getJson('/api/v1/attendance/leaves?trainee_id='.$zero->id.'&date=2026-11-27')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'accepted');
        $this->getJson('/api/v1/attendance/leaves?status=pending')->assertJsonValidationErrors('status');
        $this->getJson('/api/v1/attendance/trainees?supervisor_id='.$other->trainer_id.'&search='.urlencode($other->user->email))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other->id);
        $this->getJson('/api/v1/attendance/trainees?program_id='.$zero->application->training_program_id.'&search='.$zero->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $zero->id);
        $this->getJson('/api/v1/attendance/trainees?sort=not_allowed')->assertJsonValidationErrors('sort');
    }

    public function test_reassignment_transfers_historical_absence_and_leave_access(): void
    {
        $trainee = $this->attendanceTrainee();
        $former = $trainee->trainer->user;
        $incoming = $this->attendanceTrainee()->trainer;
        $absence = Absence::factory()->create(['trainee_id' => $trainee->id, 'trainer_id' => $trainee->trainer_id]);
        $leave = $this->attendanceLeave($trainee, ['status' => 'expired']);
        $trainee->update(['trainer_id' => $incoming->id]);

        $lists = [
            '/api/v1/absence/findAll' => $absence->id,
            '/api/v1/attendance/absences' => $absence->id,
            '/api/v1/vacation_request/listForTrainer' => $leave->id,
        ];
        foreach ($lists as $url => $id) {
            $this->actingAs($former, 'api')->getJson($url)->assertOk()->assertJsonCount(0, 'data');
            $this->actingAs($incoming->user, 'api')->getJson($url)->assertOk()->assertJsonPath('data.0.id', $id);
        }

        $url = "/api/v1/vacation_request/find/{$leave->id}";
        $this->actingAs($former, 'api')->getJson($url)->assertForbidden();
        $this->actingAs($incoming->user, 'api')->getJson($url)->assertOk()->assertJsonPath('data.status', 'expired');
    }
}
