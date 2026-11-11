<?php

namespace App\Http\Controllers\Attendance;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreAbsenceRequest;
use App\Http\Resources\Attendance\AbsenceResource;
use App\Services\Attendance\AbsenceService;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    use ApiResponse;
    public function __construct(
        protected AbsenceService $absenceService
    ) {}

    public function findAll(Request $request)
    {
        $absences = $this->absenceService->allAbsences($request);
        return $this->success(AbsenceResource::collection($absences));
    }




    public function recordAbsence(int $trainee_id ,StoreAbsenceRequest $request)
    {
        $absence=$this->absenceService->recordAbsence($trainee_id ,$request['reason'],$request->user('api'));
        return $this->success(AbsenceResource::make($absence));
    }
}
