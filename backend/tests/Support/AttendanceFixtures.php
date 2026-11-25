<?php

namespace Tests\Support;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\Department;
use App\Models\Role;
use App\Models\Trainee;
use App\Models\Trainer;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Support\Str;

trait AttendanceFixtures
{
    private function attendanceUser(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['role' => $role]));

        return $user;
    }

    private function attendanceTrainee(): Trainee
    {
        $department = Department::factory()->create(['name' => 'Attendance test '.Str::uuid()]);
        $trainer = Trainer::factory()->forUser($this->attendanceUser(UserRole::TRAINER))->forDepartment($department)->create();
        $program = TrainingProgram::factory()->create([
            'start_date' => '2026-11-22',
            'end_date' => '2026-12-15',
        ]);
        $application = Application::factory()->forProgram($program)->create(['status' => ApplicationStatus::ACTIVE]);

        return Trainee::factory()->create([
            'user_id' => $this->attendanceUser(UserRole::TRAINEE)->id,
            'application_id' => $application->id,
            'trainer_id' => $trainer->id,
            'department_id' => $department->id,
        ]);
    }

    private function attendanceLeave(Trainee $trainee, array $attributes = []): VacationRequest
    {
        return VacationRequest::factory()->create($attributes + [
            'trainee_id' => $trainee->id,
            'trainer_id' => $trainee->trainer_id,
            'start_date' => '2026-11-27',
            'end_date' => '2026-11-30',
        ]);
    }
}
