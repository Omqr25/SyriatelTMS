<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VacationRequestStatus;
use App\Helpers\ApiCode;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\Attendance\AttendanceEnrollment;
use Illuminate\Auth\Access\Response;

class VacationRequestPolicy
{

    public function decide(User $user, VacationRequest $vacation_request): Response
    {
        if (! $user->trainer) {
            return Response::deny(__('vacation_request.not_trainer'), ApiCode::FORBIDDEN);
        }

        if ($vacation_request->trainer_id !== $user->trainer->id) {
            return Response::deny(__('vacation_request.not_your_request'), ApiCode::FORBIDDEN);
        }

        if ($vacation_request->status !== VacationRequestStatus::PENDING) {
            return Response::deny(__('vacation_request.not_pending_decide'), ApiCode::FORBIDDEN);
        }

        return $this->enrollmentAccess($vacation_request);
    }


    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */

    public function view(User $user, VacationRequest $vacation_request): Response
    {
        if ($user->trainee && $vacation_request->trainee_id === $user->trainee->id) {
            return Response::allow();
        }

        $roles = $user->roles->pluck('role');
        if ($roles->containsStrict(UserRole::TRAINING_TEAM)
            || ($roles->containsStrict(UserRole::TRAINER)
                && $user->trainer
                && $vacation_request->trainer_id === $user->trainer->id)) {
            return Response::allow();
        }

        return Response::deny(__('vacation_request.unauthorized_view'), ApiCode::FORBIDDEN);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): Response
    {
        if (! $user->trainee) {
            return Response::deny(__('trainee.not_a_trainee', ['id' => $user->id]), ApiCode::FORBIDDEN);
        }

        if ($user->status !== UserStatus::ACTIVE) {
            return Response::deny(__('trainee.not_active'), ApiCode::FORBIDDEN);
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VacationRequest $vacation_request): Response
    {
        if (! $user->trainee || $vacation_request->trainee_id !== $user->trainee->id) {
            return Response::deny(__('vacation_request.unauthorized_update'), ApiCode::FORBIDDEN);
        }

        if ($vacation_request->status !== VacationRequestStatus::PENDING) {
            return Response::deny(__('vacation_request.not_pending_update'), ApiCode::FORBIDDEN);
        }

        return $this->editAccess($vacation_request);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VacationRequest $vacation_request): Response
    {
        if (! $user->trainee || $vacation_request->trainee_id !== $user->trainee->id) {
            return Response::deny(__('vacation_request.unauthorized_delete'), ApiCode::FORBIDDEN);
        }

        if ($vacation_request->status !== VacationRequestStatus::PENDING) {
            return Response::deny(__('vacation_request.not_pending_delete'), ApiCode::FORBIDDEN);
        }

        return $this->editAccess($vacation_request);
    }


    private function enrollmentAccess(VacationRequest $leave): Response
    {
        return AttendanceEnrollment::isOpen($leave->trainee, today())
            ? Response::allow()
            : Response::deny(__('attendance.inactive_enrollment'), ApiCode::FORBIDDEN);
    }

    private function editAccess(VacationRequest $leave): Response
    {
        if (! $leave->start_date || $leave->start_date->toDateString() <= today()->toDateString()) {
            return Response::deny(__('attendance.leave_already_started'), ApiCode::FORBIDDEN);
        }

        return $this->enrollmentAccess($leave);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, VacationRequest $vacationRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, VacationRequest $vacationRequest): bool
    {
        return false;
    }
}
