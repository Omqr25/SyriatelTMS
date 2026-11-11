<?php

namespace App\Policies;

use App\Enums\EvaluationRequestStatus;
use App\Enums\UserRole;
use App\Models\Evaluation;
use App\Models\ReevaluationRequest;
use App\Models\Trainee;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EvaluationPolicy
{

    public function requestReevaluation(User $user , Evaluation $evaluation): Response
    {
        $reevaluation_request = ReevaluationRequest::where('trainee_id', $user->trainee?->id)->first();
        if ($reevaluation_request) {
            return Response::deny(
                __('evaluation.request_reevaluation_error')
            );
        }
        $evaluation=Evaluation::where('trainee_id',$user->trainee?->id)->exists();
        if (!$evaluation) {
            return Response::deny(__('evaluation.no_evaluation_for_trainee'));
        }

        return Response::allow();
    }

    public function decideReevaluation(User $user, ReevaluationRequest $request): Response {
        if ((int) $request->trainer_id !== (int) $user->trainer?->id) {
            return Response::deny(
                __('evaluation.not_my_reevaluation')
            );
        }

        if ($request->status !== EvaluationRequestStatus::PENDING) {
            return Response::deny(
                __('evaluation.decided_reevaluation')
            );
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can view any models.
     */

    public function viewAny(User $user,  $trainee_id): Response
    {
        $trainee=Trainee::find($trainee_id);

        if ( $trainee->trainer == $user->trainer || $user->roles()->where('role', UserRole::TRAINING_TEAM)->exists()) {
            return Response::allow();
        }

        return Response::deny(
            __('evaluation.evaluation_of_trainee_unauthorized')
        );
    }



    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Evaluation $evaluation): Response
    {
        if ((int) $evaluation->trainee_id === (int) $user->trainee?->id) {
            return Response::allow();
        }

        if ((int) $evaluation->trainer_id === (int) $user->trainer?->id) {
            return Response::allow();
        }

        return Response::deny(
            __('evaluation.unauthorized_view')
        );
    }
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, int $trainee_id): Response
    {
        if (! $user->trainer()) {
            return Response::deny(
                __('evaluation.unauthorized_create')
            );
        }
        $isAssigned = $user->trainer?->trainees()
            ->whereKey($trainee_id)
            ->exists();

        if (! $isAssigned) {
            return Response::deny(
                __('evaluation.not_my_trainee')
            );
        }
        $evaluation = Evaluation::where('trainee_id', $trainee_id)->first();
        if ($evaluation)
        {
            return Response::deny(__('evaluation.evaluation_already_submitted'));
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can update the model.
     */

    public function update(User $user, Evaluation $evaluation): Response
    {
        if ((int) $evaluation->trainer_id !== (int) $user->trainer?->id) {
            return Response::deny(
                __('evaluation.unauthorized_update')
            );
        }


        return Response::allow();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Evaluation $evaluation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Evaluation $evaluation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Evaluation $evaluation): bool
    {
        return false;
    }
}
