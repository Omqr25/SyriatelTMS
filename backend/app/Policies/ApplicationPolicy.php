<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Application;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ApplicationPolicy
{
    /**
     * Determine whether the user can view any models.
     */

    public function acceptOrReject(User $user, Application $application): Response {

        $trainer = $user->trainer;
        if (!$trainer) {
            return Response::deny(__('application.trainer_profile_required'));
        }

        $isAssigned = $application->assigns()->where('trainer_id', $trainer->id)->exists();
        if (!$isAssigned) {
            return Response::deny(__('application.application_not_assigned_to_trainer'));
        }

        return Response::allow();
    }

    public function viewAnswers(User $user, Application $application): bool
    {
        $roles = $user->roles()->pluck('role');
        if ($roles->containsStrict(UserRole::TRAINING_TEAM)) {
            return true;
        }
        if (! $roles->containsStrict(UserRole::TRAINER)) {
            return false;
        }

        $trainer = $user->trainer;

        return $trainer !== null
            && Application::query()->whereKey($application->id)->assignedTo($trainer)->exists();
    }

    public function downloadAttachment(User $user, Application $application): bool
    {
        return $user->trainee?->application_id === $application->id
            || $this->viewAnswers($user, $application);
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Application $application): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Application $application): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Application $application): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Application $application): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Application $application): bool
    {
        return false;
    }
}
