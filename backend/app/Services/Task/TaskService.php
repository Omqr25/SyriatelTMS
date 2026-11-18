<?php

namespace App\Services\Task;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Enums\TraineeStatus;
use App\Enums\UserStatus;
use App\Models\Program;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;
use App\Exceptions\ErrorException;
use App\Helpers\ApiCode;
use App\Models\Evidence;
use App\Models\Feedback;
use App\Models\Milestone;
use App\Models\Notification;
use App\Models\Task;
use App\Models\Trainee;
use App\Models\User;
use App\Notifications\TaskEvidenceSubmittedNotification;
use App\Services\File\FileStorageService;
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
            ->when((int) auth('api')->user()->trainer?->id !== (int) $milestone->program->trainer_id,
                fn ($query) => $query->where('status', '!=', TaskStatus::DRAFT->value))
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

    public function store(int $milestoneId, array $data)
    {
        $user = auth('api')->user();
        $milestone = $this->findMilestone($milestoneId);

        return DB::transaction(function () use ($milestone, $user, $data) {
            $program = Program::lockForUpdate()->findOrFail($milestone->program_id);
            Gate::forUser($user)->authorize('update', $program);
            $phase = $program->milestones()->findOrFail($milestone->id);
            $tasks = $this->createForPhase($phase, $program, $data, $user);
            return array_key_exists('trainee_ids', $data) ? $tasks : $tasks[0];
        });
    }

    // Called inside the task or program creation transaction.
    public function createForPhase(Milestone $phase, Program $program, array $data, User $user): array
    {
        $data['deadline'] = $this->validateDeadline($program, $data['deadline']);
        $ids = $data['trainee_ids'] ?? (isset($data['trainee_id']) ? [$data['trainee_id']] : []);
        $attachments = $data['attachments'] ?? [];
        unset($data['trainee_ids'], $data['trainee_id'], $data['attachments']);

        $tasks = [];
        foreach ($ids ?: [null] as $id) {
            $trainee = $id === null ? null : $this->eligibleTrainee($program, $id);
            $task = $phase->tasks()->create($data + [
                'trainee_id' => $id,
                'status' => $trainee ? TaskStatus::PENDING : TaskStatus::DRAFT,
            ]);
            $this->saveAttachments($task, $attachments);
            if ($trainee) {
                $this->notifyTaskUser($trainee->user, $user, $task, 'New task assigned', 'task_assigned');
            }
            $tasks[] = $task->load(['trainee', 'attachments']);
        }
        return $tasks;
    }

    public function update(int $taskId, array $data): Task
    {
        return DB::transaction(function () use ($taskId, $data) {
            $existing = Task::findOrFail($taskId);
            $program = Program::lockForUpdate()->findOrFail($existing->milestone->program_id);
            $task = Task::lockForUpdate()->findOrFail($taskId);
            $user = auth('api')->user();
            Gate::forUser($user)->authorize('update', $task);

            if (isset($data['deadline'])) {
                $data['deadline'] = $this->validateDeadline($program, $data['deadline']);
            }

            $trainee = null;
            if (array_key_exists('trainee_id', $data) && (int) $task->trainee_id !== (int) $data['trainee_id']) {
                if ($data['trainee_id'] === null || !$task->deadline->isFuture() && !isset($data['deadline'])
                    || !in_array($task->status, [TaskStatus::DRAFT, TaskStatus::PENDING], true)) {
                    throw ValidationException::withMessages(['trainee_id' => __('task.cannot_reassign')]);
                }
                $trainee = $this->eligibleTrainee($program, $data['trainee_id']);
                $data['status'] = TaskStatus::PENDING;
            }

            $attachments = $data['attachments'] ?? null;
            unset($data['attachments']);
            $task->fill($data);
            if (!$task->isDirty() && ($attachments === null || $task->attachments()->orderBy('id')->pluck('path')->all() === $attachments)) {
                throw ValidationException::withMessages(['task' => __('task.no_changes')]);
            }

            if ($task->status === TaskStatus::OVERDUE && $task->isDirty('deadline') && $task->deadline->isFuture()) {
                $lastFeedback = $task->evidences()->latest('id')->first()?->targetFeedback;
                $task->status = $lastFeedback?->status === TaskStatus::NeedFix
                    ? TaskStatus::NeedFix
                    : ($task->started_at ? TaskStatus::IN_PROGRESS : TaskStatus::PENDING);
            }
            $task->save();

            if ($attachments !== null) {
                $task->attachments()->delete();
                $this->saveAttachments($task, $attachments);
            }
            if ($trainee) {
                $this->notifyTaskUser($trainee->user, $user, $task, 'New task assigned', 'task_assigned');
            }
            return $task->refresh()->load(['trainee', 'attachments']);
        });
    }

    public function delete(int $taskId): void
    {
        DB::transaction(function () use ($taskId) {
            $task = Task::lockForUpdate()->findOrFail($taskId);
            Gate::forUser(auth('api')->user())->authorize('delete', $task);
            $task->attachments()->delete();
            $task->delete();
        });
    }

    private function validateDeadline(Program $program, string $deadline): Carbon
    {
        $date = Carbon::parse($deadline)->setTimezone(config('app.timezone'));
        if ($program->deadline && !$date->lt($program->deadline)) {
            throw ValidationException::withMessages(['deadline' => __('task.before_program_deadline')]);
        }
        return $date;
    }

    public function eligibleTrainees(Program $program)
    {
        return Trainee::query()->with('user')
            ->where('trainer_id', $program->trainer_id)
            ->where('department_id', $program->department_id)
            ->where('status', TraineeStatus::ACTIVE)
            ->whereHas('application', fn ($query) => $query->where('training_program_id', $program->training_program_id))
            ->whereHas('user', fn ($query) => $query->where('status', UserStatus::ACTIVE)
                ->whereHas('roles', fn ($roles) => $roles->where('role', UserRole::TRAINEE)));
    }

    private function eligibleTrainee(Program $program, int $id): Trainee
    {
        $trainee = $this->eligibleTrainees($program)->find($id);
        if (!$trainee) {
            throw ValidationException::withMessages(['trainee_id' => __('trainee.trainee_not_in_team')]);
        }
        return $trainee;
    }

    private function saveAttachments(Task $task, array $attachments): void
    {
        $task->attachments()->createMany(array_map(fn ($path) => ['path' => $path], $attachments));
    }

    private function notifyTaskUser(User $recipient, User $sender, Task $task, string $title, string $type, array $details = []): void
    {
        Notification::create([
            'title' => $title,
            'description' => $task->name,
            'notifier_id' => $sender->id,
        ])->notified()->create(['notified_id' => $recipient->id]);

        DB::afterCommit(function () use ($recipient, $title, $task, $type, $details) {
            try {
                $recipient->notify(new TaskEvidenceSubmittedNotification($title, $task->name,
                    ['type' => $type, 'task_id' => $task->id] + $details));
            } catch (\Throwable $exception) {
                report($exception);
            }
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
            $this->notifyTaskUser($task->trainee->user, $user, $task,
                $feedback->status === TaskStatus::COMPLETED ? 'Task approved' : 'Task changes requested',
                'task_feedback_added', ['evidence_id' => $evidence->id]);
            return $feedback->load('trainer');
        });
    }

    public function addEvidence(int $task_id, User $user, array $data)
    {
        $paths = [];
        try {
            return DB::transaction(function () use ($task_id, $user, $data, &$paths) {
                $task = Task::whereKey($task_id)->lockForUpdate()->firstOrFail();
                Gate::forUser($user)->authorize('submit', $task);
                $revision = $task->status === TaskStatus::NeedFix;
                if ($revision) {
                    $previous = $task->evidences()->latest('id')->first();
                    abort_unless($previous?->targetFeedback?->status === TaskStatus::NeedFix, 422, __('task.evidence_add_error'));
                }
                $files = [];
                foreach ($data['evidences'] ?? [$data['evidence']] as $file) {
                    $path = $this->fileStorageService->store($file, "evidences/{$task->id}");
                    abort_unless($path, 500);
                    $paths[] = $path;
                    $files[] = ['path' => $path, 'name' => $file->getClientOriginalName()];
                }
                $evidence = $task->evidences()->create(['evidence' => $files[0]['path'], 'files' => $files]);
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
            if ($paths) Storage::disk('private')->delete($paths);
            throw $exception;
        }
    }

    public function downloadEvidence(int $evidence_id, int $fileIndex = 0, bool $asJson = false)
    {
        $evidence=Evidence::find($evidence_id);
        if (!$evidence) {
            throw new ErrorException(__('task.evidence_not_found'), ApiCode::NOT_FOUND);
        }
        Gate::forUser(auth('api')->user())->authorize('manageEvidence', $evidence->task);
        $file = $evidence->submittedFiles()[$fileIndex] ?? null;
        $path = $file['path'] ?? null;
        abort_unless($path && Storage::disk('private')->exists($path), 404, 'Evidence file not found.');
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('private');
        // The app saves a browser blob from JSON. Serving an attachment to XHR
        // can hand the response to a download manager before the app sees it.
        if ($asJson) {
            $bytes = $disk->get($path);
            abort_if($bytes === null || $bytes === '', 404, 'Evidence file not found.');

            return response()->json([
                'content' => base64_encode($bytes),
                'mime_type' => $disk->mimeType($path) ?: 'application/octet-stream',
            ], 200, ['Cache-Control' => 'private, no-store']);
        }

        return $disk->download($path);
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
