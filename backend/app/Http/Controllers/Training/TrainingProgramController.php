<?php

namespace App\Http\Controllers\Training;

use App\Exceptions\ErrorException;
use App\Helpers\ApiCode;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\StoreTrainingProgramRequest;
use App\Http\Requests\Training\UpdateTrainingProgramRequest;
use App\Http\Resources\Training\TrainingProgramResource;
use App\Models\TrainingProgram;
use App\Services\Training\TrainingProgramService;
use Illuminate\Http\Request;

class TrainingProgramController extends Controller
{
    use ApiResponse;

    public function __construct(protected TrainingProgramService $trainingProgramService) {}

    public function store(StoreTrainingProgramRequest $request)
    {
        $trainingPrograms = $this->trainingProgramService->create($request->validated());
        return $this->success(TrainingProgramResource::collection($trainingPrograms),
          __('training_program.create'),
            ApiCode::CREATED
        );
    }

    public function update(UpdateTrainingProgramRequest $request, int $training_program_id) {

//        dd($request->validated());
        $trainingProgram = $this->trainingProgramService->update($training_program_id, $request->validated());
        return $this->success(TrainingProgramResource::make($trainingProgram),
           __('training_program.update'),
            ApiCode::OK
        );
    }

    public function delete(int $training_program_id)
    {
        $this->trainingProgramService->delete($training_program_id);
        return $this->success(
            null,
            __('training_program.delete'),
            ApiCode::OK
        );
    }

    public function findAll(Request $request)
    {
        $page = $request['page'] ??  1;
        $limit = $request['limit'] ??  10;
        $trainingPrograms = $this->trainingProgramService->findAll($page, $limit);
//        dd(now() , $trainingPrograms);
        return $this->success(TrainingProgramResource::collection($trainingPrograms));

    }

    public function find(int $training_program_id)
    {
        $trainingProgram = $this->trainingProgramService->find($training_program_id);
        return $this->success(TrainingProgramResource::make($trainingProgram));
    }

    public function checkProgramStatus(int $training_program_id)
    {
        $status=$this->trainingProgramService->checkProgramStatus($training_program_id);
        return $this->success($status);
    }

    public function openProgram()
    {
        $openProgram=$this->trainingProgramService->openProgram();
        if (!$openProgram) {
            throw new ErrorException(__('training_program.no_open_program'),ApiCode::NOT_FOUND);
        }
        return $this->success(TrainingProgramResource::make($openProgram));
    }

}
