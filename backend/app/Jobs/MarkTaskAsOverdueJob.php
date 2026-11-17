<?php

namespace App\Jobs;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MarkTaskAsOverdueJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Task $task
    ) {}


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $task = Task::find($this->task->id);
        if (! $task || ! $task->deadline) return;
        // An edited deadline already schedules a fresh job; do not recursively
        // dispatch here (the sync driver executes delayed jobs immediately).
        if ($task->deadline->isFuture()) return;
        // Conditional update cannot overwrite a submission/approval racing this job.
        Task::whereKey($task->id)->where('deadline', '<=', now())
            ->whereIn('status', [TaskStatus::PENDING->value, TaskStatus::IN_PROGRESS->value, TaskStatus::NeedFix->value])
            ->update(['status' => TaskStatus::OVERDUE->value]);
    }
}
