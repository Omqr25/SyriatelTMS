<?php

namespace App\Services\Organization;

use App\Enums\ApplicationStatus;
use App\Enums\TraineeStatus;
use App\Enums\UserStatus;
use App\Exceptions\ErrorException;
use App\Models\Trainee;
use App\Services\Training\TrainingProgramService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TraineeService
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected TrainingProgramService $trainingProgramService)
    {
        //
    }

    public function findAll(Request $request)
    {
        $training_program=$this->trainingProgramService->openProgram();
        if (! $training_program) {
            return Trainee::whereRaw('1 = 0')->get();
        }
        return Trainee::where('status', TraineeStatus::ACTIVE)
            ->whereHas('application', function ($query) use ($training_program) {
                $query->where('training_program_id', $training_program->id);
            })->with(['user','department', 'trainer'])->get();
    }
    public function myTrainees(Request $request)
    {
        $training_program=$this->trainingProgramService->openProgram();
        $trainer=$request->user('api')->trainer;
        if (! $training_program || ! $trainer) {
            return Trainee::whereRaw('1 = 0')->get();
        }
        return Trainee::where('status', TraineeStatus::ACTIVE)
            ->where('trainer_id', $trainer->id)
            ->whereHas('application', function ($query) use ($training_program) {
                $query->where('training_program_id', $training_program->id);
            })
            ->with(['user', 'department', 'trainer', 'application'])
            ->get();
//        return Trainee::where('status' , TraineeStatus::ACTIVE)
//            ->where('trainer_id',$trainer->id)
//            ->with(['user','department', 'trainer'])->paginate(
//            perPage: $request['limit'] ?? 10,
//            page: $request['page'] ?? 1
//        );
    }

    public function find(int $trainee_id)
    {
        $trainee= Trainee::find($trainee_id);
        if (!$trainee) {
            throw new ErrorException(__('trainee.not_found'));
        }
        return $trainee;

    }
    public function fireTrainee(int $trainee_id, array $data,$actor)
    {
        $trainee=$this->find($trainee_id);
        return DB::transaction(function () use ($trainee, $data , $actor) {
            if ($trainee->fire()->exists()) {
                throw new ErrorException(__('trainee.already_fired'));
            }

            $fire = $trainee->fire()->create([
                'fired_at' => now(),
                'reason' => $data['reason'],
                'fired_by' => $actor->id,
            ]);

            $trainee->status = TraineeStatus::FIRED;
            $trainee->save();
            $user = $trainee->user;
            $user->status = UserStatus::FIRED;
            $user->save();
            $application=$trainee->application;
            $application->status=ApplicationStatus::FIRED;
            $application->save();
            return $fire->load(['trainee.user', 'firedBy']);
        });
    }


}
