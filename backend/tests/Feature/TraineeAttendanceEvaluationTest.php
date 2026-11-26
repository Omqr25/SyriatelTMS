<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureUserHasRole;
use App\Models\{User, Trainee, Trainer, Department, Application, TrainingProgram, Evaluation, Absence, ReevaluationRequest, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TraineeAttendanceEvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
    }

    private function trainee(): Trainee
    {
        $department = Department::factory()->create();
        $trainer = Trainer::factory()->create(['user_id' => User::factory()->create()->id, 'department_id' => $department->id]);
        $application = Application::factory()->create(['training_program_id' => TrainingProgram::factory()->create()->id]);
        return Trainee::factory()->create(['user_id' => User::factory()->create()->id, 'department_id' => $department->id,
            'trainer_id' => $trainer->id, 'application_id' => $application->id]);
    }

    public function test_absences_are_flat_sorted_and_scoped_to_the_logged_in_trainee(): void
    {
        $trainee = $this->trainee();
        $other = $this->trainee();
        $trainee->user->roles()->attach(Role::firstOrCreate(['role' => UserRole::TRAINEE]));
        foreach (['2026-11-01', '2026-11-03'] as $date) {
            Absence::factory()->create(['trainee_id' => $trainee->id, 'trainer_id' => $trainee->trainer_id, 'date' => $date]);
        }
        Absence::factory()->create(['trainee_id' => $other->id, 'trainer_id' => $other->trainer_id]);
        $this->actingAs($trainee->user, 'api')->getJson('/api/v1/absence/findAll')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.date', '03-11-2026')
            ->assertJsonPath('data.0.trainee_id', $trainee->id)->assertJsonPath('data.0.status', 'absent');
        $this->actingAs(User::factory()->create(), 'api')->getJson('/api/v1/absence/findAll')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_trainee_can_request_reevaluation_once_and_reload_preserves_eligibility(): void
    {
        Notification::fake();
        $this->withoutMiddleware(EnsureUserHasRole::class);
        $trainee = $this->trainee();
        Evaluation::factory()->create(['trainee_id' => $trainee->id, 'trainer_id' => $trainee->trainer_id]);
        $this->actingAs($trainee->user, 'api');
        $this->getJson('/api/v1/evaluation/me')->assertOk()->assertJsonPath('data.can_request_reevaluation', true);
        $this->postJson('/api/v1/evaluation/me/reevaluation_request', ['reason' => 'Please review my evaluation'])->assertOk();
        $this->getJson('/api/v1/evaluation/me')->assertOk()->assertJsonPath('data.can_request_reevaluation', false);
        foreach (['pending', 'accepted', 'rejected'] as $status) {
            ReevaluationRequest::query()->update(['status' => $status]);
            $this->postJson('/api/v1/evaluation/me/reevaluation_request', ['reason' => 'Please review my evaluation again'])->assertForbidden();
        }
        $this->assertDatabaseCount('reevaluation_requests', 1);
    }

    public function test_no_evaluation_cannot_be_requested_and_short_reasons_are_rejected(): void
    {
        $this->withoutMiddleware(EnsureUserHasRole::class);
        $trainee = $this->trainee();
        $this->actingAs($trainee->user, 'api');
        $this->getJson('/api/v1/evaluation/me')->assertNotFound();
        $this->postJson('/api/v1/evaluation/me/reevaluation_request', ['reason' => 'Please review my evaluation'])->assertNotFound();
        $this->postJson('/api/v1/evaluation/me/reevaluation_request', ['reason' => 'short'])->assertUnprocessable();
        $this->assertDatabaseCount('reevaluation_requests', 0);
    }
}
