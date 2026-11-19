<?php

namespace App\Services\Training;

use App\Enums\ApplicationStatus;
use App\Mail\ProgramDatesChangedMail;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Notification\InboxPublisher;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProgramDateChangeNotifier
{
    public function __construct(private InboxPublisher $inbox) {}

    public function notify(TrainingProgram $program, User $actor, array $snapshot): void
    {
        $schedule = ['id' => $program->id] + Arr::only($snapshot, ['name', 'application_start_date', 'application_end_date', 'start_date', 'end_date']);
        $program->applications()
            ->whereNotIn('status', [ApplicationStatus::REJECTED, ApplicationStatus::FIRED, ApplicationStatus::COMPLETED])
            ->with(['latestAssignment.trainer', 'trainee.trainer'])
            ->chunkById(100, function ($applications) use ($program, $actor, $schedule) {
                $recipients = [];
                foreach ($applications as $application) {
                    if ($application->trainee) {
                        $recipients[] = $application->trainee->user_id;
                    } else {
                        $email = $application->email;
                        $name = $application->full_name;
                        DB::afterCommit(function () use ($email, $name, $schedule) {
                            try {
                                Mail::to($email)->queue(new ProgramDatesChangedMail($name, $schedule));
                            } catch (Throwable $exception) {
                                Log::warning('Could not queue training program date email.', [
                                    'training_program_id' => $schedule['id'],
                                    'exception_type' => $exception::class,
                                ]);
                            }
                        });
                    }
                    $recipients[] = $application->trainee?->trainer?->user_id ?? $application->latestAssignment?->trainer?->user_id;
                }

                $recipients = array_unique(array_filter($recipients));
                if ($recipients) {
                    $this->inbox->publish(
                        $actor, $recipients, 'training_program.dates_changed', "{$program->id}:{$program->revision}",
                        'Training program dates updated',
                        "{$schedule['name']} registration runs from {$schedule['application_start_date']} to {$schedule['application_end_date']}. "
                            ."Training runs from {$schedule['start_date']} to {$schedule['end_date']}.",
                        ['training_program_id' => $program->id, 'revision' => $program->revision] + Arr::except($schedule, ['id']),
                    );
                }
            });
    }
}
