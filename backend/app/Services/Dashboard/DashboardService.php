<?php

namespace App\Services\Dashboard;

use App\Enums\ApplicationStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\ErrorException;
use App\Models\Application;
use App\Models\Department;
use App\Models\Interview;
use App\Models\Task;
use App\Models\Trainee;
use App\Models\User;
use App\Services\Training\TrainingProgramService;
use Illuminate\Support\Facades\App;

class DashboardService
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected TrainingProgramService $trainingProgramService)
    {
        //
    }

    public function HR()
    {
        $training_program = $this->trainingProgramService->openProgram();
        if (! $training_program) {
            return [
                'statistics' => [
                    'total_applicants' => 0,
                    'trainees' => 0,
                    'interviews' => 0,
                    'departments' => Department::count(),
                ],
                'recent_applicants' => [],
                'upcoming_interviews' => [],
            ];
        }

        $applications = Application::where('training_program_id', $training_program->id);
        $recentApplicants = (clone $applications)->latest('created_at')->limit(5)->get();
        $upcomingInterviews = Interview::whereHas('application', function ($query) use ($training_program) {
                $query->where('training_program_id', $training_program->id)
                ->where('status',ApplicationStatus::FOR_INTERVIEW);})
            ->where('time', '>=', now())
            ->with(['application', 'trainer.user'])
            ->orderByRaw('CASE WHEN time >= ? THEN 0 ELSE 1 END', [now()])
            ->orderBy('time','asc')
            ->limit(5)
            ->get();

        return [
            'statistics' => [
                'total_applicants' => (clone $applications)->count(),
                'trainees' => Trainee::whereHas('application', function ($query) use ($training_program) {
                        $query->where('training_program_id', $training_program->id);
                    })->count(),
                'interviews' => Interview::whereHas('application', function ($query) use ($training_program) {
                        $query->where('training_program_id', $training_program->id)->where('status',ApplicationStatus::FOR_INTERVIEW);
                    })->count(),
                'departments' => Department::count(),
            ],
            'recent_applicants' => $recentApplicants,
            'upcoming_interviews' => $upcomingInterviews,
        ];
    }


    public function trainer(User $user): array
    {
        $trainer = $user->trainer;
        $training_program = $this->trainingProgramService->openProgram();
        if (! $training_program) {
            return [
                'trainees_count' => 0,
                'tasks_count' => 0,
            ];
        }
        $trainees_count = Trainee::whereHas('application', function ($query) use ($training_program) {
            $query->where('training_program_id', $training_program->id); })
            ->where('trainer_id',$trainer->id)->count();
        $tasks_count = Task::whereHas('milestone.program', function ($query) use ($training_program) {
                $query->where('training_program_id', $training_program->id);})
            ->whereHas('milestone.program.trainer', function ($query) use ($trainer) {
                $query->where('id', $trainer->id);})->count();

        return [
            'trainees_count' => $trainees_count,
            'tasks_count' => $tasks_count,
        ];
    }

    public function trainee(User $user)
    {
        $trainee = $user->trainee;

        $tasks = $trainee->tasks();
        $assigned_tasks_count = (clone $tasks)->count();
        $completed_tasks_count = (clone $tasks)->where('status',TaskStatus::COMPLETED)->count();
        $in_progress_task = (clone $tasks)->where('status', TaskStatus::IN_PROGRESS)->orderByDesc('updated_at')->first();
        $upcoming_deadline_tasks = (clone $tasks)->where('status', '!=', TaskStatus::COMPLETED)
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [now(), now()->addDays(3),])
            ->orderBy('deadline','asc')
            ->get();

        return [
            'assigned_tasks_count' => $assigned_tasks_count,
            'completed_tasks_count' => $completed_tasks_count,
            'in_progress_task' => $in_progress_task,
            'upcoming_deadline_tasks' => $upcoming_deadline_tasks ,
        ];
    }

    public function generateTrainingCycleReport()
    {
        $training_program = $this->trainingProgramService->openProgram();
        if (!$training_program) {
            throw new ErrorException();
        }
        $applications = Application::where('training_program_id', $training_program->id);
        $totalApplications = (clone $applications)->count();
        $acceptedApplications = (clone $applications)
            ->where('status', ApplicationStatus::ACCEPTED)->count();

        $rejectedApplications = (clone $applications)
            ->where('status',ApplicationStatus::REJECTED)
            ->count();

        $trainees = Trainee::query()
            ->whereHas('application', function ($query) use ($training_program) {
                $query->where('training_program_id', $training_program->id);
            });

        $participatingTrainees = (clone $trainees)
            ->where('status', ApplicationStatus::ACTIVE->value)
            ->count();

        $completedTrainees = (clone $trainees)
            ->where('status', ApplicationStatus::COMPLETED->value)
            ->count();

        $firedTrainees = (clone $trainees)
            ->where('status',ApplicationStatus::FIRED->value)
            ->count();

        $departmentCount = Department::count();

        $activeSupervisors = User::query()
            ->where('status', UserStatus::ACTIVE->value)
            ->whereHas('roles', function ($query) {
        $query->where('role', UserRole::TRAINER->value);})
        ->count();

        return [
            'generated_at' => now()->toDateTimeString(),
            'training_program' => [
                'id' => $training_program->id,
                'name' => $training_program->name,
            ],
            'total_applications' => $totalApplications,
            'accepted_applications' => $acceptedApplications,
            'rejected_applications' => $rejectedApplications,
            'participating_trainees' => $participatingTrainees,
            'completed_trainees' => $completedTrainees,
            'fired_trainees' =>$firedTrainees,
            'total_departments' => $departmentCount,
            'active_supervisor_accounts' => $activeSupervisors,
        ];
    }


}
