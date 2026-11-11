<?php

namespace App\Http\Controllers\Training;

use App\Enums\ApplicationAttachmentTypes;
use App\Enums\ApplicationStatus;
use App\Exceptions\ErrorException;
use App\Helpers\ApiCode;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\InterviewResultRequest;
use App\Http\Requests\Training\StoreApplicationRequest;
use App\Http\Requests\Training\StoreInterviewRequest;
use App\Http\Resources\Training\AllApplicationResource;
use App\Http\Resources\Training\ApplicationResource;
use App\Http\Resources\Training\AssignedApplicationResource;
use App\Http\Resources\Training\InterviewApplicationResource;
use App\Http\Resources\Training\InterviewResource;
use App\Models\Interview;
use App\Services\File\FileStorageService;
use App\Services\Training\ApplicationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    use ApiResponse;
    use AuthorizesRequests;
    public function __construct(protected ApplicationService $applicationService ) {}

    public function store(StoreApplicationRequest $request)
    {
        $application = $this->applicationService->create($request->validated());
        return $this->success(
            ApplicationResource::make($application),
            __('application.create'),
            ApiCode::CREATED
        );
    }


    public function delete(int $application_id)
    {
        $this->applicationService->delete($application_id);

        return $this->success(__('application.delete'));
    }


    public function find( int $application_id )
    {
        $application = $this->applicationService->find($application_id);
        return $this->success(ApplicationResource::make($application));
    }

    public function findAll(Request $request)
    {
        $applications = $this->applicationService->findAll($request);
        return $this->successWithPagination(AllApplicationResource::collection($applications),['total_number'=>$applications->total()]);
    }

    public function assignedAndQualifiedApplications()
    {
        $applications = $this->applicationService->assignedAndQualifiedApplications();
        return $this->success(AssignedApplicationResource::collection($applications));
    }

    public function interviewApplications()
    {
        $applications=$this->applicationService->interviewApplications();
        return $this->success(InterviewApplicationResource::collection($applications));
    }

    public function downloadAttachment(int $application_id, ApplicationAttachmentTypes $type)
    {
        return $this->applicationService->downloadApplicationAttachment($application_id, $type);
    }

    public function assignApplication(int $application_id, int $trainer_id)
    {
        $application = $this->applicationService->assignApplication($application_id, $trainer_id);
        return $this->success(ApplicationResource::make($application),__('application.assign_application'));
    }

    public function trainerAcceptOrRejectApplicant(int $application_id, ApplicationStatus $type)
    {
        $application=$this->applicationService->trainerAcceptOrRejectApplicant($application_id,$type);
        return $this->success(ApplicationResource::make($application));
    }

    public function scheduleInterview($application_id, StoreInterviewRequest $request)
    {
         $interview=$this->applicationService->scheduleInterview($application_id, $request->validated());
         return $this->success(InterviewResource::make($interview));
    }

    public function setInterviewResult(int $interview_id, InterviewResultRequest $request) {

//        dd($request->user('api'));
        $interview = $this->applicationService->setInterviewResult($interview_id, $request->validated());
        return $this->success(InterviewResource::make($interview));
    }

    public function acceptOrRejectApplicant( int $application_id , ApplicationStatus $type)
    {
        $application=$this->applicationService->acceptOrRejectApplicant($application_id,$type);
        return $this->success(ApplicationResource::make($application));
    }
    public function activateTrainee(int $application_id)
    {
        $application=$this->applicationService->activateTrainee($application_id);
        return $this->success(ApplicationResource::make($application));
    }

    public function trainerApplications()
    {
        $trainer=auth('api')->user()->trainer;
        $trainerApplications = $this->applicationService->trainerApplications($trainer);
        return $this->success(ApplicationResource::collection($trainerApplications));
    }

    public function trainerInterviews()
    {

        $trainer=auth('api')->user()->trainer;
        $trainerInterviews = $this->applicationService->trainerInterviews($trainer);
        return $this->success(InterviewResource::collection($trainerInterviews));
    }


}
