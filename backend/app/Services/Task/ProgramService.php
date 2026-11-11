<?php

namespace App\Services\Task;

use App\Exceptions\ErrorException;
use App\Models\Program;
use App\Models\User;
use App\Services\Training\TrainingProgramService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProgramService
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected TrainingProgramService $trainingProgramService)
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

        $program= Program::withCount('milestones')->with('milestones.tasks')
            ->where('department_id', $user->trainee?->department_id)
            ->where('training_program_id', $user->trainee->application->training_program_id)
            ->first();
        if (!$program) {
            throw new ErrorException(__('program.no_program_for_you'));
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
            $data=array_merge($data,['department_id'=>$user->trainer->department_id,'training_program_id'=>$training_program->id]);
            $program = $user->trainer->programs()->create($data);
            $program->milestones()->createMany($milestones);
            return $program->load('milestones');
        });
    }
    public function find(int $program_id)
    {
        $program=Program::find($program_id);
        if (!$program) {
            throw new ErrorException(__('program.not_found'));
        }
        return $program->load('milestones.tasks');
    }
    public function update(int $program_id, array $data) {

        $program = $this->find($program_id);

        Gate::forUser(auth('api')->user())->authorize('update', $program);
        return DB::transaction(function () use ($program, $data) {
            $phases = $data['phases'] ?? [];

            unset($data['phases']);

            $program->update($data);

            $program->milestones()->delete();

            foreach ($phases as $phase_data) {
                $program->milestones()->create($phase_data);
            }

            return $program->fresh()->load('milestones');
        });

    }
    public function delete(int $program_id)
    {
        $program=$this->find($program_id);
        Gate::forUser(auth('api')->user())->authorize('delete', $program);
        $program->delete();
        $program->milestones()->delete();
    }

}
