<?php

namespace App\Http\Controllers\Attendance;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreAbsenceRequest;
use App\Http\Resources\Attendance\AbsenceResource;
use App\Services\Attendance\AbsenceService;
use App\Services\Attendance\AttendanceAccess;
use App\Services\Attendance\AttendanceLists;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    use ApiResponse;

    public function __construct(protected AbsenceService $absenceService) {}

    public function eligibleTrainees(Request $request)
    {
        return $this->success($this->absenceService->eligibleTrainees($request->user('api')));
    }

    public function findAll(Request $request)
    {
        return $this->successWithPagination(AbsenceResource::collection($this->absenceService->allAbsences($request, legacy: true)));
    }

    public function index(Request $request)
    {
        $user = $request->user('api');
        $trainee = AttendanceAccess::traineeId($user) ? AttendanceLists::withCounts(AttendanceAccess::trainees($user))->first() : null;

        return $this->successWithPagination(AbsenceResource::collection($this->absenceService->allAbsences($request)), [
            'counts' => $trainee ? AttendanceLists::counts($trainee) : null,
        ]);
    }

    public function recordAbsence(int $trainee_id, StoreAbsenceRequest $request)
    {
        $absence = $this->absenceService->recordAbsence($trainee_id, $request->validated('reason'), $request->user('api'));

        return $this->success(AbsenceResource::make($absence));
    }

    public function update(int $absence, StoreAbsenceRequest $request)
    {
        return $this->success(AbsenceResource::make(
            $this->absenceService->update($absence, $request->user('api'), $request->validated('reason')),
        ));
    }

    public function delete(int $absence, Request $request)
    {
        $this->absenceService->delete($absence, $request->user('api'));

        return $this->success();
    }
}
