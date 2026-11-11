<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Trainee;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AbsencePolicy
{

    public function record(User $user, Trainee $trainee): Response
    {
        if (!$user->trainer() || $trainee->trainer->id != $user->trainer?->id)
        {
            return Response::deny(__('absence.not_your_trainee'));
        }
        return Response::allow();
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
    public function view(User $user, Absence $absence): bool
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
    public function update(User $user, Absence $absence): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Absence $absence): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Absence $absence): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Absence $absence): bool
    {
        return false;
    }
}
