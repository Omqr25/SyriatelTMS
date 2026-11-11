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
        if ($this->task->deadline > now()) {
            self::dispatch($this->task)->delay($this->task->deadline);
            return;
        }

        if ($this->task->status !== TaskStatus::COMPLETED && $this->task->status !== TaskStatus::CANCELLED) {
            $this->task->status = TaskStatus::OVERDUE;
            $this->task->save();
        }
    }

}
