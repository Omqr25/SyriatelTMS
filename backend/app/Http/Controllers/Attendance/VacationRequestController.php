<?php

namespace App\Http\Controllers\Attendance;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\DecideVacationRequest;
use App\Http\Requests\Attendance\StoreVacationRequest;
use App\Http\Requests\Attendance\UpdateVacationRequest;
use App\Http\Resources\Attendance\VacationRequestResource;
use App\Models\VacationRequest;
use App\Services\Attendance\VacationRequestService;
use Illuminate\Http\Request;

class VacationRequestController extends Controller
{
    use ApiResponse;
    public function __construct(
        protected VacationRequestService $vacationRequestService
    ) {}

    public function ListForTrainer(Request $request)
    {
        $requests = $this->vacationRequestService->listForTrainer($request);
        return $this->success(VacationRequestResource::collection($requests));
    }
    public function ListForTrainee(Request $request)
    {
        $requests = $this->vacationRequestService->listForTrainee($request);

        return $this->success(VacationRequestResource::collection($requests));
    }

    public function store(StoreVacationRequest $request)
    {
        $vacation_request = $this->vacationRequestService->create($request->user('api'),$request->validated());
        return $this->success(VacationRequestResource::make($vacation_request));
    }

    public function find(int $request_id)
    {
        $vacationRequest = $this->vacationRequestService->find($request_id);
        return $this->success(
            new VacationRequestResource($vacationRequest),
            'Vacation request retrieved successfully.'
        );
    }

    public function update(UpdateVacationRequest $request, int $request_id)
    {
        $vacation_request = $this->vacationRequestService->update($request_id, $request->validated());
        return $this->success( VacationRequestResource::make($vacation_request));
    }

    public function cancel(int $request_id)
    {
        $this->vacationRequestService->cancel($request_id);
        return $this->success();
    }

    public function decide(int $request_id ,DecideVacationRequest $request)
    {
        $vacation_request = $this->vacationRequestService->decide($request_id,$request['status']);
        return $this->success(VacationRequestResource::make($vacation_request));
    }
}
