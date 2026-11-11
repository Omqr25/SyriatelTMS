<?php

namespace App\Http\Controllers\Organization;

use App\Exceptions\ErrorException;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\FireTraineeRequest;
use App\Http\Resources\Organization\AllTraineeResource;
use App\Http\Resources\Organization\FiredTraineeResource;
use App\Http\Resources\User\ProfileResource;
use App\Http\Resources\User\TraineeResource;
use App\Services\Organization\TraineeService;
use Illuminate\Http\Request;
use function CuyZ\Valinor\Compiler\this;

class TraineeController extends Controller
{
    use ApiResponse;
    public function __construct(protected TraineeService $traineeService)
    {

    }
     public function findAll(Request $request)
     {
         $trainees = $this->traineeService->findAll($request);
         return $this->success( AllTraineeResource::collection($trainees));
     }

     public function myTrainees(Request $request)
     {
         $trainees = $this->traineeService->myTrainees($request);
         return $this->success(AllTraineeResource::collection($trainees));
     }

     public function fireTrainee(int $trainee_id , FireTraineeRequest $request)
     {
         $fire = $this->traineeService->fireTrainee($trainee_id,$request->validated(),$request->user('api'));
         return $this->success(FiredTraineeResource::make($fire));
     }

     public function find(int $trainee_id)
     {
         $trainee = $this->traineeService->find($trainee_id);
         return $this->success(TraineeResource::make($trainee));
     }

    public function getProfile(Request $request)
    {
        $user = $request->user();
        $user->load(['trainee.application']);
        if (!$user->trainee || !$user->trainee->application) {
            throw new ErrorException(__('trainee.not_found'));
        }
        return $this->success(ProfileResource::make($user));
    }


}
