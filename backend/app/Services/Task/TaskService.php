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
use App\Notifications\TaskEvidenceSubmittedNotification;
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

    public function find(int $task_id): Task
    {
        $task = Task::with([
            'trainee',
            'attachments',
            'evidences.targetFeedback.trainer',
        ])->find($task_id);
    
        if (!$task) {
            throw new ErrorException(
                __('task.not_found'),
                ApiCode::NOT_FOUND
            );
        }
    
        Gate::forUser(auth('api')->user())->authorize('view', $task);
    
        return $task;
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
                if ($trainee->department_id != $milestone->program->department_id || $trainee->trainer_id != $milestone->program->trainer_id) {
                    throw new ErrorException (__('trainee.trainee_not_in_team'));
                }
    //            dd($milestone->program->training_program_id);
                if ($trainee->application->training_program_id != $milestone->program->training_program_id) {
                    throw new ErrorException (__('trainee.trainee_not_in_training_program'));
                }
                $data['status'] = TaskStatus::PENDING->value;
                $data['priority'] = $data['priority'] ?? 5;
                $task = $milestone->tasks()->create($data);

                $task->attachments()->createMany(collect($data['attachments'] ?? [])
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
            if (array_key_exists('attachments', $data)) {
                $task->attachments()->delete();
                $task->attachments()->createMany(collect($data['attachments'] ?? [])
                    ->map(fn (string $path) => ['path' => $path])->all());
            }
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



    public function addFeedback(int $task_id, array $data, User $user)
    {
        return DB::transaction(function () use ($task_id, $data, $user) {
            $task = Task::whereKey($task_id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('manageFeedback', $task);
            $evidence = $task->evidences()->latest('id')->first();
            abort_unless($task->status === TaskStatus::ForReview && $evidence
                && (int) $evidence->id === (int) $data['evidence_id']
                && ! $evidence->targetFeedback()->exists(), 422, __('task.feedback_error'));
            $feedback = Feedback::create([
                'evidence_id' => $evidence->id, 'trainer_id' => $user->trainer->id,
                'feedback' => $data['feedback'] ?? null, 'status' => $data['status'],
            ]);
            $task->update(['status' => $feedback->status,
                'completed_at' => $feedback->status === TaskStatus::COMPLETED ? now() : null]);
            return $feedback->load('trainer');
        });
    }

    public function addEvidence(int $task_id, User $user, array $data)
    {
        $path = null;
        try {
            return DB::transaction(function () use ($task_id, $user, $data, &$path) {
                $task = Task::whereKey($task_id)->lockForUpdate()->firstOrFail();
                Gate::forUser($user)->authorize('submit', $task);
                $revision = $task->status === TaskStatus::NeedFix;
                if ($revision) {
                    $previous = $task->evidences()->latest('id')->first();
                    abort_unless($previous?->targetFeedback?->status === TaskStatus::NeedFix, 422, __('task.evidence_add_error'));
                }
                $path = $this->fileStorageService->store($data['evidence'], "evidences/{$task->id}");
                abort_unless($path, 500);
                $evidence = $task->evidences()->create(['evidence' => $path]);
                $task->update(['status' => TaskStatus::ForReview, 'completed_at' => null]);
                $trainer = $task->milestone->program->trainer->user;
                $title = $revision ? 'Revised task evidence' : 'Task ready for review';
                $notification = Notification::create(['title' => $title,
                    'description' => $user->name . ': ' . $task->name, 'notifier_id' => $user->id]);
                $notification->notified()->create(['notified_id' => $trainer->id]);
                DB::afterCommit(function () use ($trainer, $title, $task, $evidence) {
                    try {
                        $trainer->notify(new TaskEvidenceSubmittedNotification($title, $task->name,
                            ['type' => 'task_evidence_submitted', 'task_id' => $task->id, 'evidence_id' => $evidence->id]));
                    } catch (\Throwable $exception) {
                        // The in-app notification and evidence are durable. A push
                        // outage must not report a failed submission or delete its file.
                        report($exception);
                    }
                });
                return $evidence->load('task');
            });
        } catch (\Throwable $exception) {
            if ($path) Storage::disk('private')->delete($path);
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
        return DB::transaction(function () use ($task_id) {
            $task = Task::whereKey($task_id)->lockForUpdate()->firstOrFail();
            Gate::forUser(auth('api')->user())->authorize('start', $task);
            $task->update(['status' => TaskStatus::IN_PROGRESS, 'started_at' => now()]);
            return $task->fresh();
        });
    }
}
