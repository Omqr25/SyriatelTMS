<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Department;
use App\Models\Role;
use App\Models\Trainee;
use App\Models\Trainer;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class TraineeProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_contains_the_trainees_own_training_and_application_details(): void
    {
        config(['jwt.secret' => str_repeat('test-secret-', 8)]);

        $traineeUser = User::factory()->create(['name' => 'Lina Saleh']);
        $traineeUser->roles()->attach(Role::firstOrCreate(['role' => 'TRAINEE']));
        $supervisor = User::factory()->create(['name' => 'Rana Khalil']);
        $department = Department::factory()->create(['name' => 'Network Operations']);
        $trainer = Trainer::factory()->forUser($supervisor)->forDepartment($department)->create();
        $program = TrainingProgram::factory()->create(['name' => 'Network Engineering Internship']);
        $application = Application::factory()->create(['training_program_id' => $program->id]);
        Trainee::factory()->create([
            'user_id' => $traineeUser->id,
            'trainer_id' => $trainer->id,
            'department_id' => $department->id,
            'application_id' => $application->id,
        ]);

        $this->getJson('/api/v1/profile')->assertUnauthorized();

        $this->withToken(JWTAuth::fromUser($traineeUser))->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.personal_information.name', $traineeUser->name)
            ->assertJsonPath('data.contact_information.email', $traineeUser->email)
            ->assertJsonPath('data.educational_information.university', $application->university)
            ->assertJsonPath('data.application_id', $application->id)
            ->assertJsonPath('data.training_information', [
                'direct_supervisor' => $supervisor->name,
                'department' => $department->name,
                'training_program' => $program->name,
            ])
            ->assertJsonStructure(['data' => ['attachments' => ['cv', 'national_id', 'academic_qualification', 'personal_photo']]]);
    }
}
