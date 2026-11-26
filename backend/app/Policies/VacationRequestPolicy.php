<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Enums\VacationRequestStatus;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\Attendance\AttendanceAccess;
use App\Services\Attendance\AttendanceEnrollment;
use Illuminate\Auth\Access\Response;

class VacationRequestPolicy
{
    public function view(User $user, VacationRequest $leave): bool
    {
        if (AttendanceAccess::isHr($user)) {
            return $leave->status === VacationRequestStatus::ACCEPTED;
        }

        return $leave->trainee_id === AttendanceAccess::traineeId($user)
            || AttendanceAccess::supervises($user, $leave->trainee);
    }

    public function create(User $user): bool
    {
        return AttendanceAccess::traineeId($user) !== null && $user->status === UserStatus::ACTIVE;
    }

    public function update(User $user, VacationRequest $leave): Response
    {
        if ($leave->trainee_id !== AttendanceAccess::traineeId($user)) {
            return Response::deny(__('vacation_request.unauthorized_update'));
        }

        if ($leave->status !== VacationRequestStatus::PENDING) {
            return Response::deny(__('vacation_request.not_pending_update'));
        }

        return $this->enrollmentAccess($leave);
    }

    public function delete(User $user, VacationRequest $leave): Response
    {
        if ($leave->status === VacationRequestStatus::ACCEPTED
            && AttendanceAccess::supervises($user, $leave->trainee)) {
            return $this->enrollmentAccess($leave);
        }

        if ($leave->status === VacationRequestStatus::PENDING
            && $leave->trainee_id === AttendanceAccess::traineeId($user)) {
            return $this->enrollmentAccess($leave);
        }

        return Response::deny(__('vacation_request.unauthorized_delete'));
    }

    public function decide(User $user, VacationRequest $leave): Response
    {
        if (! AttendanceAccess::supervises($user, $leave->trainee)) {
            return Response::deny(__('vacation_request.not_your_request'));
        }

        if ($leave->status !== VacationRequestStatus::PENDING) {
            return Response::deny(__('vacation_request.not_pending_decide'));
        }

        return $this->enrollmentAccess($leave);
    }

    private function enrollmentAccess(VacationRequest $leave): Response
    {
        return AttendanceEnrollment::isOpen($leave->trainee, today())
            ? Response::allow()
            : Response::deny(__('attendance.inactive_enrollment'));
    }
}
