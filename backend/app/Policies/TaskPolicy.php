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
        $program = $milestone->program;
        return ($user->trainer && (int) $program->trainer_id === (int) $user->trainer->id)
            || $this->inTeam($user, $milestone)
            ? Response::allow() : Response::deny(__('task.unauthorized_view'));
    }

    private function inTeam(User $user, Milestone $milestone): bool
    {
        $trainee = $user->trainee;
        $program = $milestone->program;
        return $trainee && $trainee->status === \App\Enums\TraineeStatus::ACTIVE
            && $user->status === \App\Enums\UserStatus::ACTIVE
            && (int) $trainee->department_id === (int) $program->department_id
            && (int) $trainee->trainer_id === (int) $program->trainer_id
            && (int) $trainee->application?->training_program_id === (int) $program->training_program_id;
    }

    public function workOn(User $user, Task $task): bool
    {
        return $this->inTeam($user, $task->milestone)
            && (int) $task->trainee_id === (int) $user->trainee->id
            && (! $task->deadline || $task->deadline->isFuture());
    }

    public function start(User $user, Task $task): bool
    {
        return $this->workOn($user, $task) && $task->status === TaskStatus::PENDING;
    }

    public function submit(User $user, Task $task): bool
    {
        return $this->workOn($user, $task)
            && in_array($task->status, [TaskStatus::IN_PROGRESS, TaskStatus::NeedFix], true);
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
        return $this->ownsTask($user, $task)
            || ($this->inTeam($user, $task->milestone) && (int) $task->trainee_id === (int) $user->trainee->id)
            ? Response::allow() : Response::deny(__('task.unauthorized_manage_evidence'));
    }


    public function manageAttachments(User $user, Task $task): Response
    {
        if (!$this->ownsTask($user, $task)) {
            return Response::deny(
                __('task.unauthorized_manage_attachments')
            );
        }

        if ($task->status == TaskStatus::COMPLETED) {
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

        return $task->status !== TaskStatus::DRAFT && $this->inTeam($user, $task->milestone)
            ? Response::allow() : Response::deny(__('task.unauthorized_view'));
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

        if ($task->status === TaskStatus::COMPLETED) {
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

        if ($task->status == TaskStatus::COMPLETED || $task->status == TaskStatus::OVERDUE) {
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


        if ($task->status !== TaskStatus::DRAFT || $task->trainee_id || $task->evidences()->exists()) {
            return Response::deny(
                __('task.draft_only_delete')
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
