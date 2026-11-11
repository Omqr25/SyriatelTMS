<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProgramPolicy
{
    private function owns(User $user, Program $program): bool
    {
        return $user->trainer !== null
            && (int) $program->trainer_id === (int) $user->trainer->id;
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
    public function view(User $user, Program $program): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->trainer !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Program $program): Response
    {
        return $this->owns($user, $program)
            ? Response::allow()
            : Response::deny(__('program.unauthorized_update'));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Program $program): Response
    {
        return $this->owns($user, $program)
            ? Response::allow()
            : Response::deny(__('program.unauthorized_delete'));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Program $program): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Program $program): bool
    {
        return false;
    }
}
