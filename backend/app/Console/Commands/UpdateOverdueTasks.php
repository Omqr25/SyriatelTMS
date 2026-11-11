<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:update-overdue-tasks')]
#[Description('Command description')]
class UpdateOverdueTasks extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Task::query()
            ->where('deadline', '<', now())
            ->whereNotIn('status', [TaskStatus::CANCELLED->value, TaskStatus::CANCELLED->value, TaskStatus::OVERDUE->value])
            ->update(['status' => TaskStatus::OVERDUE->value]);

        $this->info('Overdue tasks updated.');

        return self::SUCCESS;
    }
}
