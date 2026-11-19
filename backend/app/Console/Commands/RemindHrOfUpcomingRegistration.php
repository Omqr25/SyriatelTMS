<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Notification\InboxPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemindHrOfUpcomingRegistration extends Command
{
    protected $signature = 'training-programs:remind-hr';

    protected $description = 'Notify active HR users of registration dates within the next seven days';

    public function handle(InboxPublisher $inbox): int
    {
        $hr = User::where('status', UserStatus::ACTIVE)
            ->whereHas('roles', fn ($query) => $query->where('role', UserRole::TRAINING_TEAM))
            ->pluck('id');
        if ($hr->isEmpty()) {
            return self::SUCCESS;
        }

        $today = today();
        $deadline = $today->copy()->addWeek();
        TrainingProgram::whereDate('application_start_date', '>', $today->toDateString())
            ->whereDate('application_start_date', '<=', $deadline->toDateString())
            ->select('id')->eachById(function (TrainingProgram $candidate) use ($inbox, $hr, $today, $deadline) {
                DB::transaction(function () use ($candidate, $inbox, $hr, $today, $deadline) {
                    $program = TrainingProgram::lockForUpdate()->find($candidate->id);
                    if (! $program || $program->application_start_date->lte($today)
                        || $program->application_start_date->gt($deadline)) {
                        return;
                    }

                    $date = $program->application_start_date->toDateString();
                    $action = $program->archived_at
                        ? ' Review and publish the program to enable registration.'
                        : '';
                    $inbox->publish(
                        null, $hr, 'training_program_registration_reminder', "{$program->id}:{$date}",
                        'Upcoming training program registration',
                        "{$program->name} is scheduled to begin registration on {$date}.{$action}",
                        ['training_program_id' => $program->id, 'application_start_date' => $date],
                    );
                }, 3);
            });

        return self::SUCCESS;
    }
}
