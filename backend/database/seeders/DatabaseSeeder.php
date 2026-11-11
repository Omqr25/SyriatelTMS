<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\TraineeStatus;
use App\Enums\UserRole as UserRoleEnum;
use App\Models\Application;
use App\Models\ApplicationAssign;
use App\Models\Department;
use App\Models\Role;
use App\Models\Trainee;
use App\Models\Trainer;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\UserRole as PivotUserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::transaction(function () {

            /*
            |--------------------------------------------------------------------------
            | 1. Roles
            |--------------------------------------------------------------------------
            */

            $trainingTeamRole = Role::create([
                'role' => UserRoleEnum::TRAINING_TEAM->value,
            ]);

            $trainerRole = Role::create([
                'role' => UserRoleEnum::TRAINER->value,
            ]);

            $traineeRole = Role::create([
                'role' => UserRoleEnum::TRAINEE->value,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 2. Training Team
            |--------------------------------------------------------------------------
            */

            $trainingTeam = User::factory()->create([
                'name' => 'Training Team',
                'email' => 'training_team@syriatel.test',
            ]);

            PivotUserRole::factory()->create([
                'user_id' => $trainingTeam->id,
                'role_id' => $trainingTeamRole->id,
                'assigned_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | 3. Departments
            |--------------------------------------------------------------------------
            */

            $departments = Department::factory()
                ->count(3)
                ->create();

            $softwareDepartment = $departments->first();

            /*
            |--------------------------------------------------------------------------
            | 4. Training Program
            |--------------------------------------------------------------------------
            */

            $trainingProgram = TrainingProgram::factory()->create([
                'name' => 'Syriatel Software Engineering Internship',
            ]);

            /*
            |--------------------------------------------------------------------------
            | 5. Trainers
            |--------------------------------------------------------------------------
            */

            $trainerUsers = User::factory()
                ->count(3)
                ->create();

            $trainers = collect();

            foreach ($trainerUsers as $trainerUser) {

                // Assign trainer role
                PivotUserRole::factory()->create([
                    'user_id' => $trainerUser->id,
                    'role_id' => $trainerRole->id,
                    'assigned_at' => now(),
                ]);

                // Create trainer profile
                $trainer = Trainer::factory()->create([
                    'user_id' => $trainerUser->id,
                    'department_id' => $softwareDepartment->id,
                ]);

                $trainers->push($trainer);
            }

            /*
            |--------------------------------------------------------------------------
            | 6. Applications
            |--------------------------------------------------------------------------
            */

            $applications = Application::factory()
                ->count(3)
                ->accepted()
                ->forProgram($trainingProgram)
                ->create();

            /*
            |--------------------------------------------------------------------------
            | 7. Trainees & Application Assignments
            |--------------------------------------------------------------------------
            */

            foreach ($applications as $index => $application) {

                $trainer = $trainers[$index];

                // Assign trainer to application
                $assignment = ApplicationAssign::create([
                    'application_id' => $application->id,
                    'trainer_id' => $trainer->id,
                    'status' => ApplicationStatus::ASSIGNED->value,
                ]);

                // Create trainee user
                $traineeUser = User::factory()->create();

                // Assign trainee role
                PivotUserRole::factory()->create([
                    'user_id' => $traineeUser->id,
                    'role_id' => $traineeRole->id,
                    'assigned_at' => now(),
                ]);

                // Create trainee profile
                Trainee::factory()->create([
                    'user_id' => $traineeUser->id,
                    'status' => TraineeStatus::ACTIVE,
                    'department_id' => $softwareDepartment->id,
                    'trainer_id' => $assignment->trainer_id,
                    'application_id' => $assignment->application_id,
                ]);
            }
        });
    }
}
