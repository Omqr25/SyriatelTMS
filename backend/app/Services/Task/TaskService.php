<?php

namespace App\Services\Task;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Exceptions\ErrorException;
use App\Helpers\ApiCode;
use App\Jobs\MarkTaskAsOverdueJob;
use App\Models\Evidence;
use App\Models\Feedback;
use App\Models\Milestone;
use App\Models\Notification;
use App\Models\Task;
use App\Models\Trainee;
use App\Models\User;
use App\Notifications\TmsPushNotification;
use App\Services\File\FileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class TaskService
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected FileStorageService $fileStorageService)
    {
        //
    }

    public function findAll(int $milestone_id)
    {
        $milestone=$this->findMilestone($milestone_id);
        Gate::forUser(auth('api')->user())->authorize('viewMilestone',  $milestone );
        return $milestone->tasks()
            ->with(['trainee', 'attachments'])
            ->orderByDesc('created_at')
            ->get();
    }



    public function findMilestone(int $milestone_id)
    {
        $milestone=Milestone::find($milestone_id);
        if (!$milestone)
        {
            throw new ErrorException(__('milestone.not_found'));
        }
        return $milestone;
    }
    // public function find(int $task_id)
    // {
    //     $task=Task::find($task_id);
    //     if (!$task) {
    //         throw new ErrorException(__('task.not_found'),ApiCode::NOT_FOUND);
    //     }
    //     Gate::forUser(auth('api')->user())->authorize('view', $task);
    //     $can_update=($task->trainee_id == auth('api')->user()->trainee?->id);
    //     return [$task->load(['trainee', 'attachments']) ,$can_update];
    // }

    public function find(int $task_id): Task
    {
        $task = Task::find($task_id);
        if (!$task) {
            throw new ErrorException(__('task.not_found'), ApiCode::NOT_FOUND);
        }
        Gate::forUser(auth('api')->user())->authorize('view', $task);

        return $task->load(['trainee', 'attachments']);
    }

    public function store(int $milestone_id, array $data)
    {
            $milestone=$this->findMilestone($milestone_id);
            Gate::forUser(auth('api')->user())->authorize('viewMilestone', [Task::class, $milestone]);
            return DB::transaction(function () use ($milestone, $data) {

                $trainee = Trainee::find($data['trainee_id']);
                if (!$trainee) {
                    throw new ErrorException(__('trainee.not_found'), ApiCode::NOT_FOUND);
                }
                $isTrainee = $trainee->user->roles->contains(fn ($role)   => $role->role->value === UserRole::TRAINEE->value);
                if (!$isTrainee) {
                    throw new ErrorException (__('trainee.not_a_trainee', ['id' => $trainee->id]));
                }
                if ($trainee->department_id != $milestone->program->department_id) {
                    throw new ErrorException (__('trainee.trainee_not_in_team'));
                }
    //            dd($milestone->program->training_program_id);
                if ($trainee->application->training_program_id != $milestone->program->training_program_id) {
                    throw new ErrorException (__('trainee.trainee_not_in_training_program'));
                }
                $data['status'] = TaskStatus::PENDING->value;
                $data['priority'] = $data['priority'] ?? 5;
                $task = $milestone->tasks()->create($data);

                $task->attachments()->createMany(collect($data['attachments'])
                    ->map(fn (string $path) => ['path' => $path,])->all());
                MarkTaskAsOverdueJob::dispatch($task)->delay($task->deadline);
                $notification = Notification::create([
                    'title' => 'New Task',
                    'description' => 'your Supervisor assigned task to you , start working .',
                    'notifier_id' => $task->milestone->program->trainer->user->id,
                ]);

                $notification->notified()->create([
                    'notified_id' => $trainee->user->id,
                ]);
                $trainee->user->notify(new TmsPushNotification(
                    title: 'New Task',
                    body: 'your Supervisor assigned task to you , start working',
                    data: [
                        'type' => 'task assigned',
                        'task_id' => $task->id,
                    ],
                ));
                return $task->load(['trainee', 'attachments']);
            });
    }

    public function update(int $task_id, array $data)
    {
        $task=$this->find($task_id);
        Gate::forUser(auth('api')->user())->authorize('update', $task);
        return DB::transaction(function () use ($task, $data) {
            $task->update($data);
            $task->attachments()->delete();
            $task->attachments()->createMany(collect($data['attachments'])
                ->map(fn (string $path) => ['path' => $path,])->all());
            MarkTaskAsOverdueJob::dispatch($task)->delay($task->deadline);

            return $task->fresh()->load(['trainee', 'attachments']);
        });
    }

    public function delete(int $task_id): void
    {
        $task=$this->find($task_id);
        Gate::forUser(auth('api')->user())->authorize('delete', $task);
        DB::transaction(function () use ($task) {
            $task->attachments()->delete();
            $task->delete();
        });
    }



    public function addFeedback(int $task_id, array $data , User $user)
    {
        $task=$this->find($task_id);
        Gate::forUser(auth('api')->user())->authorize('manageFeedback', $task);
        $trainee=Evidence::find($data['evidence_id'])->trainee;
        if ($task->status !== TaskStatus::ForReview) {
            throw new ErrorException(__('task.feedback_error'),ApiCode::BAD_REQUEST);
        }
        $data=array_merge($data, ['trainer_id'=>$user->id]);
        $feedback=Feedback::create($data);
        $task->update(['status' => $feedback->status]);
        return $feedback->fresh()->load('trainer');
    }

    public function addEvidence(int $task_id, User $user, array $data)
    {
        $task=$this->find($task_id);
        Gate::forUser(auth('api')->user())->authorize('manageEvidence', $task);
        if (!in_array($task->status, [TaskStatus::IN_PROGRESS, TaskStatus::NeedFix], true)) {
            throw new ErrorException(__('task.evidence_add_error'),ApiCode::BAD_REQUEST);
        }

        $file = $data['evidence'];
        $path = $this->fileStorageService->store($file, "evidences/{$task->id}");

        try {
            return DB::transaction(function () use ($task, $data, $file, $path) {

                $status = $task->status instanceof \BackedEnum
                    ? $task->status->value
                    : $task->status;

                if (!in_array($status, [TaskStatus::IN_PROGRESS->value,TaskStatus::NeedFix->value], true)) {
                    throw new ErrorException(__('task.evidence_add_error'),ApiCode::BAD_REQUEST);
                }

                $evidence = $task->evidences()->create([
                    'evidence' => $path,
                ]);

                $task->status = TaskStatus::ForReview->value;
                $task->save();

                return $evidence->load('task');
            });
        } catch (\Throwable $exception) {
            Storage::disk('private')->delete($path);
            throw $exception;
        }
    }

    public function downloadEvidence(int $evidence_id)
    {
        $evidence=Evidence::find($evidence_id);
        if (!$evidence) {
            throw new ErrorException(__('task.evidence_not_found'), ApiCode::NOT_FOUND);
        }
        Gate::forUser(auth('api')->user())->authorize('manageEvidence', $evidence->task);
        abort_unless(Storage::disk('private')->exists($evidence->evidence), 404, 'Evidence file not found.');
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('private');
        return $disk->download($evidence->evidence);
    }

    public function startTask(int $task_id)
    {
        $task=$this->find($task_id);
        Gate::forUser(auth('api')->user())->authorize('updateStatus', $task);
        if ($task->status !== TaskStatus::PENDING) {
            throw new ErrorException(__('task.task_status_error'),ApiCode::BAD_REQUEST);
        }
        $task->update(['status'=>TaskStatus::IN_PROGRESS->value,'started_at'=>now()]);
        return $task->refresh();
    }


}
