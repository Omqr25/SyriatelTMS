<?php

namespace App\Http\Controllers\Attendance;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Attendance\TraineeAttendanceResource;
use App\Services\Attendance\AttendanceAccess;
use App\Services\Attendance\AttendanceLists;
use Illuminate\Http\Request;

class AttendanceRecordsController extends Controller
{
    use ApiResponse;

    public function __construct(private AttendanceLists $lists) {}

    public function index(Request $request)
    {
        $this->authorizeStaff($request);

        return $this->successWithPagination(TraineeAttendanceResource::collection($this->lists->records($request)));
    }

    public function show(Request $request, int $trainee)
    {
        $this->authorizeStaff($request);
        $record = $this->lists->record($request->user('api'), $trainee);

        return $this->success(TraineeAttendanceResource::make($record));
    }

    public function options(Request $request)
    {
        $this->authorizeStaff($request);

        return $this->success($this->lists->options($request->user('api')));
    }

    private function authorizeStaff(Request $request): void
    {
        $user = $request->user('api');
        abort_unless(AttendanceAccess::isHr($user) || AttendanceAccess::supervisorId($user), 403);
    }
}
