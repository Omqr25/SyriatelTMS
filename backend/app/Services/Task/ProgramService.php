<?php

namespace App\Services\Task;

use App\Exceptions\ErrorException;
use App\Helpers\ApiCode;
use App\Models\Program;
use App\Enums\TaskStatus;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Services\Training\TrainingProgramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProgramService
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected TrainingProgramService $trainingProgramService, protected TaskService $taskService)
    {
        //
    }

    public function ownedPrograms(Request $request)
    {
        return Program::where('trainer_id', $request->user('api')->trainer?->id)->withCount('milestones')->orderBy('created_at', 'desc')
            ->get();
    }
    public function myProgram(User $user)
    {

        $trainee = $user->trainee;
        $trainingProgramId = $trainee?->application?->training_program_id;

        if (!$trainee || !$trainingProgramId || $trainee->status !== \App\Enums\TraineeStatus::ACTIVE || $user->status !== \App\Enums\UserStatus::ACTIVE) {
            throw new ErrorException(__('program.no_program_for_you'), ApiCode::NOT_FOUND);
        }

        // Team membership requires the same supervisor, department and training cycle.
        $program = Program::withCount('milestones')
            ->with(['milestones.tasks' => fn ($query) => $query->where('status', '!=', TaskStatus::DRAFT->value)->with(['trainee', 'attachments'])])
            ->where('training_program_id', $trainingProgramId)
            ->where('department_id', $trainee->department_id)
            ->where('trainer_id', $trainee->trainer_id)->first();

        if (!$program) {
            throw new ErrorException(__('program.no_program_for_you'), ApiCode::NOT_FOUND);
        }
        return $program;
    }
    public function store(User $user, array $data)
    {
        Gate::forUser($user)->authorize('create', Program::class);
        $training_program=$this->trainingProgramService->openProgram();
        if (!$training_program) {
            throw new ErrorException(__('program.no_open_training'));
        }
        $program=Program::where('training_program_id', $training_program->id)->where('trainer_id',$user->trainer->id)->exists();
        if ($program) {
            throw new ErrorException(__('program.program_in_this_training_program_exist'));
        }
        return DB::transaction(function () use ($user, $data, $training_program) {
            $milestones = $data['phases'];
            unset($data['phases']);
            $data['description'] = $data['description'] ?? '';
            $data=array_merge($data,['department_id'=>$user->trainer->department_id,'training_program_id'=>$training_program->id]);
            $program = $user->trainer->programs()->create($data);
            foreach ($milestones as $index => $phaseData) {
                $tasks = $phaseData['tasks'] ?? [];
                unset($phaseData['tasks']);
                $phase = $program->milestones()->create($phaseData);
                foreach ($tasks as $taskIndex => $task) {
                    try {
                        $this->taskService->createForPhase($phase, $program, $task, $user);
                    } catch (ValidationException $exception) {
                        $errors = [];
                        foreach ($exception->errors() as $field => $messages) {
                            $errors["phases.$index.tasks.$taskIndex.$field"] = $messages;
                        }
                        throw ValidationException::withMessages($errors);
                    }
                }
            }
            return $program->load('milestones.tasks.trainee', 'milestones.tasks.attachments');
        });
    }
    public function taskCandidates(User $user, ?int $programId): array
    {
        $program = $programId ? Program::findOrFail($programId) : null;
        if ($program) Gate::forUser($user)->authorize('view', $program);
        if (!$program) {
            Gate::forUser($user)->authorize('create', Program::class);
            $cycle = $this->trainingProgramService->openProgram();
            if (!$cycle) return [];
            $program = new Program([
                'trainer_id' => $user->trainer->id,
                'department_id' => $user->trainer->department_id,
                'training_program_id' => $cycle->id,
            ]);
        }
        return $this->taskService->eligibleTrainees($program)->get()
            ->map(fn ($trainee) => ['id' => $trainee->id, 'name' => $trainee->user->name])
            ->sortBy('name')->values()->all();
    }

    public function find(int $programId): Program
    {
        $program = Program::findOrFail($programId);
        Gate::forUser(auth('api')->user())->authorize('view', $program);
        return $program->load('milestones.tasks.trainee', 'milestones.tasks.attachments');
    }

    public function update(int $programId, array $data): Program
    {
        return DB::transaction(function () use ($programId, $data) {
            $program = Program::lockForUpdate()->findOrFail($programId);
            Gate::forUser(auth('api')->user())->authorize('update', $program);

            if (isset($data['deadline']) && $program->milestones()
                ->whereHas('tasks', fn ($query) => $query->where('deadline', '>=', $data['deadline']))->exists()) {
                throw ValidationException::withMessages(['deadline' => __('task.before_program_deadline')]);
            }

            $phases = $data['phases'] ?? null;
            unset($data['phases']);
            if (array_key_exists('description', $data)) $data['description'] ??= '';
            $program->update($data);

            if ($phases !== null) {
                $existingIds = $program->milestones()->pluck('id');
                $keptIds = array_column($phases, 'id');
                $removed = $program->milestones()->whereIn('id', $existingIds->diff($keptIds));
                if ((clone $removed)->has('tasks')->exists()) {
                    throw ValidationException::withMessages(['phases' => __('task.phase_has_tasks')]);
                }
                $removed->delete();

                foreach ($phases as $phaseData) {
                    if (isset($phaseData['id'])) {
                        $phase = $program->milestones()->findOrFail($phaseData['id']);
                        unset($phaseData['id']);
                        $phase->update($phaseData);
                    } else {
                        $program->milestones()->create($phaseData);
                    }
                }
            }
            return $program->load('milestones.tasks');
        });
    }

    public function delete(int $programId): void
    {
        DB::transaction(function () use ($programId) {
            $program = Program::lockForUpdate()->findOrFail($programId);
            Gate::forUser(auth('api')->user())->authorize('delete', $program);
            if ($program->milestones()->has('tasks')->exists()) {
                throw ValidationException::withMessages(['program' => __('task.phase_has_tasks')]);
            }
            $program->delete();
        });
    }
}
