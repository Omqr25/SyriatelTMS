<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Exceptions\ErrorException;
use App\Mail\ApplicationDecisionMail;
use App\Models\Application;
use App\Models\Department;
use App\Models\Role;
use App\Models\Trainer;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Training\ApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
        Mail::fake();
        $hr = User::factory()->create();
        $hr->roles()->attach(Role::firstOrCreate(['role' => 'HR']));
        $this->actingAs($hr, 'api');
    }

    public function test_final_states_cannot_be_rejected_or_accepted_again(): void
    {
        foreach (
            ['accepted', 'active', 'completed', 'fired', 'rejected'] as $status
        ) {
            $application = Application::factory()->forProgram(TrainingProgram::factory()->create())->create([
                'status' => $status,
            ]);
            foreach (['accepted', 'rejected'] as $decision) {
                $this->getJson(
                    "/api/v1/application/acceptOrRejectApplicant/{$application->id}/{$decision}",
                )->assertStatus(400);
                $this->assertSame(
                    $status,
                    $application->fresh()->status->value,
                );
            }
        }
        Mail::assertNothingQueued();
    }

    public function test_acceptance_requires_an_interview_and_queues_the_decision(): void
    {
        $application = Application::factory()->forProgram(TrainingProgram::factory()->create())->create([
            'status' => 'qualified',
        ]);
        $this->getJson(
            "/api/v1/application/acceptOrRejectApplicant/{$application->id}/accepted",
        )->assertStatus(400);
        $application->update(['status' => 'interviewed']);
        $this->getJson(
            "/api/v1/application/acceptOrRejectApplicant/{$application->id}/accepted",
        )->assertOk();
        $this->assertSame(
            ApplicationStatus::ACCEPTED,
            $application->fresh()->status,
        );
        Mail::assertQueued(
            ApplicationDecisionMail::class,
            fn ($mail) => $mail->accepted,
        );
        Mail::assertNothingSent();
    }

    public function test_recruitment_states_can_be_rejected_but_arbitrary_decisions_are_invalid(): void
    {
        foreach (
            ['pending', 'assigned', 'qualified', 'for interview', 'interviewed'] as $status
        ) {
            $application = Application::factory()->forProgram(TrainingProgram::factory()->create())->create([
                'status' => $status,
            ]);
            $this->getJson(
                "/api/v1/application/acceptOrRejectApplicant/{$application->id}/active",
            )->assertStatus(400);
            $this->getJson(
                "/api/v1/application/acceptOrRejectApplicant/{$application->id}/rejected",
            )->assertOk();
            $this->assertSame(
                ApplicationStatus::REJECTED,
                $application->fresh()->status,
            );
        }
        Mail::assertQueuedCount(5);
    }

    public function test_only_pending_applications_can_be_assigned_and_only_qualified_can_be_scheduled(): void
    {
        $service = app(ApplicationService::class);
        foreach (ApplicationStatus::cases() as $status) {
            $application = Application::factory()->forProgram(TrainingProgram::factory()->create())->create([
                'status' => $status,
            ]);
            if ($status !== ApplicationStatus::PENDING) {
                try {
                    $service->assignApplication($application->id, 0);
                    $this->fail('Invalid assignment was permitted');
                } catch (ErrorException $error) {
                    $this->assertSame(400, $error->errorCode);
                }
            }
            if ($status !== ApplicationStatus::QUALIFIED) {
                try {
                    $service->scheduleInterview($application->id, []);
                    $this->fail('Invalid interview was permitted');
                } catch (ErrorException $error) {
                    $this->assertSame(400, $error->errorCode);
                }
            }
            $this->assertSame($status, $application->fresh()->status);
        }
    }

    public function test_activation_without_assignment_returns_a_client_error_instead_of_crashing(): void
    {
        $application = Application::factory()->forProgram(TrainingProgram::factory()->create())->create(['status' => 'accepted']);
        $this->getJson(
            "/api/v1/application/activateTrainee/{$application->id}",
        )->assertStatus(400);
        $this->assertSame(
            ApplicationStatus::ACCEPTED,
            $application->fresh()->status,
        );
    }

    public function test_queued_acceptance_stays_accepted_after_activation(): void
    {
        $application = Application::factory()->forProgram(TrainingProgram::factory()->create())->create(['status' => 'accepted']);
        $mail = new ApplicationDecisionMail($application);
        $application->update(['status' => 'active']);
        $restored = unserialize(serialize($mail));
        $this->assertTrue($restored->accepted);
        $this->assertSame(
            'Your Syriatel Training Application Was Accepted',
            $restored->envelope()->subject,
        );
    }

    public function test_activation_is_once_only_and_active_trainees_use_dismissal(): void
    {
        Role::firstOrCreate(['role' => 'TRAINEE']);
        $department = Department::factory()->create();
        $trainer = Trainer::factory()->create([
            'department_id' => $department->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $application = Application::factory()->forProgram(TrainingProgram::factory()->create())->create(['status' => 'accepted']);
        $application
            ->assigns()
            ->create(['trainer_id' => $trainer->id, 'status' => 'assigned']);
        Queue::fake();
        $this->getJson(
            "/api/v1/application/activateTrainee/{$application->id}",
        )->assertOk();
        $this->getJson(
            "/api/v1/application/activateTrainee/{$application->id}",
        )->assertStatus(400);
        $trainee = $application->trainee()->firstOrFail();
        $this->assertSame(1, $application->trainee()->count());
        $this->getJson(
            "/api/v1/application/acceptOrRejectApplicant/{$application->id}/rejected",
        )->assertStatus(400);
        $this->postJson("/api/v1/trainee/fire/{$trainee->id}", [
            'reason' => 'Documented HR decision',
        ])->assertOk();
        $this->assertSame('fired', $application->fresh()->status->value);
        $this->assertSame('fired', $trainee->fresh()->status->value);
        $this->assertSame('fired', $trainee->user->fresh()->status->value);
        $this->assertSame(
            'Documented HR decision',
            $trainee->fire()->firstOrFail()->reason,
        );
        $this->postJson("/api/v1/trainee/fire/{$trainee->id}", [
            'reason' => 'Duplicate',
        ])->assertStatus(400);
    }

    public function test_assignment_and_interview_refresh_status_and_reject_duplicate_scheduling(): void
    {
        Queue::fake();
        $department = Department::factory()->create();
        $trainer = Trainer::factory()->create([
            'department_id' => $department->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $application = Application::factory()->forProgram(TrainingProgram::factory()->create())->create(['status' => 'pending']);
        $programId = $application->training_program_id;
        $this->getJson("/api/v1/application/{$application->id}/assign/{$trainer->id}")->assertOk();
        $this->assertSame(ApplicationStatus::ASSIGNED, $application->fresh()->status);
        $this->assertSame($programId, $application->fresh()->training_program_id);
        $this->getJson("/api/v1/application/{$application->id}/assign/{$trainer->id}")->assertStatus(400);
        $this->assertSame(1, $application->assigns()->count());
        $application->update(['status' => 'qualified']);
        $body = ['trainer_id' => $trainer->id, 'time' => now()->addDay()->setTime(10, 0)->toDateTimeString(), 'location' => 'HQ'];
        $this->postJson("/api/v1/application/{$application->id}/interview", $body)->assertOk();
        $this->postJson("/api/v1/application/{$application->id}/interview", $body)->assertStatus(400);
        $this->assertSame(1, $application->interviews()->count());
        $this->assertSame(ApplicationStatus::FOR_INTERVIEW, $application->fresh()->status);
    }
}
