<?php

namespace App\Policies;

use App\Enums\TaskStatus;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{

    public function viewMilestone(User $user, Milestone $milestone): Response
    {
        $assigned_trainee=$user->trainee?->department_id == $milestone->program->department_id;
        if (!$user->trainer && !$assigned_trainee) {
            return Response::deny(
                __('task.not_trainer')
            );
        }
        $program = $milestone->program;
        if ( !$program || ($user->trainer &&((int) $program->trainer_id !== (int) $user->trainer?->id))) {
            return Response::deny(
             __('task.unauthorized_management')
            );
        }

        return Response::allow();
    }



    public function manageFeedback(User $user, Task $task): Response
    {
        if (!$this->ownsTask($user, $task)) {
            return Response::deny(
                __('task.unauthorized_manage_feedback')
            );
        }

        return Response::allow();
    }

    public function manageEvidence(User $user, Task $task): Response
    {
        if (!$task->trainee?->id == $user->trainee?->id && !$this->ownsTask($user, $task)) {
            return Response::deny(
                __('task.unauthorized_manage_evidence')
            );
        }
        return Response::allow();
    }


    public function manageAttachments(User $user, Task $task): Response
    {
        if (!$this->ownsTask($user, $task)) {
            return Response::deny(
                __('task.unauthorized_manage_attachments')
            );
        }

        if ($task->status == TaskStatus::COMPLETED->value) {
            return Response::deny(
                __('task.unauthorized_manage_attachment_completed')
            );
        }

        return Response::allow();
    }

    private function ownsTask(User $user, Task $task): bool
    {
        $trainer = $user->trainer;
        if (!$trainer) {
            return false;
        }

        $program = $task->milestone?->program;
        return $program !== null && $program->trainer_id === $trainer?->id;
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
    public function view(User $user, Task $task): Response
    {
        if ($this->ownsTask($user, $task)) {
            return Response::allow();
        }

        $isAssignedTrainee = ($task->trainee_id == $user->trainee?->id);

        if ($isAssignedTrainee) {
            return Response::allow();
        }
        $trainee_in_team= ($task->milestone->program->training_program_id == $user->trainee?->application->training_program_id
            || $task->milestone->program->department_id == $user->trainee?->department_id
        );
        if ($trainee_in_team)
        {
            return Response::allow();
        }
        return Response::deny(
            __('task.unauthorized_view')
        );
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

    public function update(User $user, Task $task): Response
    {
        if (!$this->ownsTask($user, $task)) {
            return Response::deny(
                __('task.unauthorized_update')
            );
        }

        if ($task->status == TaskStatus::COMPLETED->value || $task->status==TaskStatus::OVERDUE->value) {
            return Response::deny(
                __('task.completed_task_update')
            );
        }

        return Response::allow();
    }

    public function updateStatus(User $user, Task $task): Response
    {
        if (!$this->ownsTask($user, $task) && $task->trainee_id !== $user->trainee?->id) {
            return Response::deny(
                __('task.unauthorized_update')
            );
        }

        if ($task->status == TaskStatus::COMPLETED->value || $task->status == TaskStatus::OVERDUE->value) {
            return Response::deny(
                __('task.completed_task_update')
            );
        }

        return Response::allow();
    }




    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Task $task): Response
    {
        if (!$this->ownsTask($user, $task)) {
            return Response::deny(
                __('task.unauthorized_delete')
            );
        }


        if ($task->status  == TaskStatus::COMPLETED->value) {
            return Response::deny(
                __('task.completed_task_delete')
            );
        }

        return Response::allow();
    }


    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Task $task): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Task $task): bool
    {
        return false;
    }
}
