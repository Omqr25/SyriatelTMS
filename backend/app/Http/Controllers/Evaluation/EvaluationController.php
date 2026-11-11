<?php

namespace App\Http\Controllers\Evaluation;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\DecideReevaluationRequest;
use App\Http\Requests\Evaluation\StoreEvaluationRequest;
use App\Http\Requests\Evaluation\StoreReevaluationRequest;
use App\Http\Resources\Evaluation\EvaluationResource;
use App\Http\Resources\Task\ReevaluationRequestResource;
use App\Models\Evaluation;
use App\Models\ReevaluationRequest;
use App\Services\Evaluation\EvaluationService;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    use ApiResponse;
    public function __construct(
        protected EvaluationService $evaluationService
    ) {}

    public function findAll(Request $request)
    {
        $evaluations = $this->evaluationService->findAll($request);
        return $this->success(EvaluationResource::collection($evaluations));
    }
    public function store(StoreEvaluationRequest $request, int $trainee_id)
    {
        $trainer = $request->user('api');
        $evaluation = $this->evaluationService->create($trainer, $trainee_id, $request->validated());
        return $this->success(EvaluationResource::make($evaluation));

    }

    public function showTrainee(int $trainee_id)
    {
        $evaluations=$this->evaluationService->showTrainee($trainee_id);
        return $this->success(EvaluationResource::collection($evaluations));
    }

    public function myEvaluation(Request $request)
    {
        $user = $request->user();
        $evaluation=$this->evaluationService->myEvaluation($user);
        return $this->success(['evaluation' =>EvaluationResource::make($evaluation[0]),'reevaluation' =>$evaluation[1]]);

    }
    public function myEvaluations(Request $request)
    {
        $user = $request->user();
        $evaluations=$this->evaluationService->myEvaluations($user);
        return $this->success(EvaluationResource::collection($evaluations));

    }


    public function requestReevaluate(StoreReevaluationRequest $request )
    {

        $reevaluation_request = $this->evaluationService->requestReevaluate($request->validated('reason'));
        return $this->success(ReevaluationRequestResource::make($reevaluation_request));
    }

    public function reevaluationRequests(Request $request)
    {
        $reevaluation_requests = $this->evaluationService->reevaluationRequests($request);
        return $this->success(ReevaluationRequestResource::collection($reevaluation_requests));
    }

    public function decide(DecideReevaluationRequest $request, int $request_id) {
        $reevaluation = $this->evaluationService->decide($request_id, $request->validated());
        return  $this->success(ReevaluationRequestResource::make($reevaluation));
    }


}
