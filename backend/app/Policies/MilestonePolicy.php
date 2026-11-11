<?php

namespace App\Policies;

use App\Models\Milestone;
use App\Models\Program;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MilestonePolicy
{


    private function ownsProgram(User $user, ?Program $program): bool {
        return $program !== null
            && $user->trainer !== null
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
    public function view(User $user, Milestone $milestone): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Program $program): Response
    {
        return $this->ownsProgram($user, $program)
            ? Response::allow()
            : Response::deny(__('program.unauthorized_milestone_create'));
    }


    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Milestone $milestone): Response
    {
        return $this->ownsProgram($user, $milestone->program)
            ? Response::allow()
            : Response::deny(__('program.unauthorized_milestone_update'));
    }

    /**
     * Determine whether the user can delete the model.
     */

    public function delete(User $user, Milestone $milestone): Response
    {
        return $this->ownsProgram($user, $milestone->program)
            ? Response::allow()
            : Response::deny(__('program.unauthorized_milestone_delete'));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Milestone $milestone): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Milestone $milestone): bool
    {
        return false;
    }
}
