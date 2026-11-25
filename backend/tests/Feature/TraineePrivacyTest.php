<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Department;
use App\Models\Role;
use App\Models\Trainee;
use App\Models\Trainer;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TraineePrivacyTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private Trainer $assigned;

    private Trainer $unassigned;

    private Trainee $trainee;

    private Trainee $otherTrainee;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
        $this->hr = $this->userWithRole('HR');
        $department = Department::factory()->create();
        $this->assigned = Trainer::factory()->forUser($this->userWithRole('SUPERVISOR'))->forDepartment($department)->create();
        $this->unassigned = Trainer::factory()->forUser($this->userWithRole('SUPERVISOR'))->forDepartment($department)->create();
        $program = TrainingProgram::factory()->create([
            'start_date' => today()->subDay(),
            'end_date' => today()->addWeek(),
        ]);

        $this->trainee = $this->enroll($this->assigned, $program);
        $this->otherTrainee = $this->enroll($this->unassigned, $program);
    }

    public function test_vacation_details_are_visible_only_to_the_owner_assigned_supervisor_and_hr(): void
    {
        $leave = VacationRequest::factory()->create([
            'trainee_id' => $this->trainee->id,
            'trainer_id' => $this->assigned->id,
            'reason' => 'Private test reason',
            'start_date' => today()->addDay(),
            'end_date' => today()->addDay(),
        ]);
        $url = "/api/v1/vacation_request/find/{$leave->id}";
        $this->getJson($url)->assertUnauthorized();
        foreach ([$this->unassigned->user, $this->otherTrainee->user] as $user) {
            $this->actingAs($user, 'api')->getJson($url)->assertForbidden();
        }
        foreach ([$this->trainee->user, $this->assigned->user, $this->hr] as $user) {
            $this->actingAs($user, 'api')->getJson($url)->assertOk()
                ->assertJsonPath('data.reason', $leave->reason)
                ->assertJsonPath('data.trainee.name', $this->trainee->user->name)
                ->assertJsonMissingPath('data.trainee.email')
                ->assertJsonMissingPath('data.trainee.phone_number');
        }

        $this->actingAs($this->assigned->user, 'api')->getJson('/api/v1/vacation_request/listForTrainer')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.trainee.email');
        $this->actingAs($this->unassigned->user, 'api')->getJson('/api/v1/vacation_request/listForTrainer')
            ->assertOk()->assertJsonPath('data', []);
        $this->actingAs($this->otherTrainee->user, 'api')->getJson('/api/v1/vacation_request/listForTrainee')
            ->assertOk()->assertJsonPath('data', []);

        $this->putJson("/api/v1/vacation_request/update/{$leave->id}", ['reason' => 'Changed'])->assertForbidden();
        $this->deleteJson("/api/v1/vacation_request/{$leave->id}")->assertForbidden();
        $this->actingAs($this->trainee->user, 'api')->putJson("/api/v1/vacation_request/update/{$leave->id}", ['reason' => 'Changed'])
            ->assertOk()->assertJsonPath('data.reason', 'Changed');
    }

    public function test_trainee_contact_permissions_do_not_grant_access_to_the_original_application(): void
    {
        $traineeUrl = "/api/v1/trainee/{$this->trainee->id}";
        $applicationUrl = "/api/v1/application/{$this->trainee->application_id}";
        foreach ([$this->assigned->user, $this->hr] as $user) {
            $this->actingAs($user, 'api')->getJson($traineeUrl)->assertOk()
                ->assertJsonPath('data.email', $this->trainee->user->email);
        }
        $this->getJson($applicationUrl)->assertOk()->assertJsonPath('data.email', $this->trainee->application->email);
        $this->actingAs($this->assigned->user, 'api')->getJson($applicationUrl)->assertForbidden();

        foreach ([$this->unassigned->user, $this->otherTrainee->user] as $user) {
            $this->actingAs($user, 'api')->getJson($traineeUrl)->assertForbidden();
            $this->getJson($applicationUrl)->assertForbidden();
        }

        $this->actingAs($this->trainee->user, 'api')->getJson($applicationUrl)->assertForbidden();
        $this->getJson('/api/v1/profile')->assertOk()
            ->assertJsonPath('data.contact_information.email', $this->trainee->user->email)
            ->assertJsonPath('data.application_id', $this->trainee->application_id);
    }

    private function enroll(Trainer $trainer, TrainingProgram $program): Trainee
    {
        $application = Application::factory()->forProgram($program)->create(['status' => ApplicationStatus::ACTIVE]);

        return Trainee::factory()->create([
            'user_id' => $this->userWithRole('TRAINEE')->id,
            'application_id' => $application->id,
            'trainer_id' => $trainer->id,
            'department_id' => $trainer->department_id,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['role' => $role]));

        return $user;
    }
}
