<?php

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Enums\TraineeStatus;
use App\Enums\TrainingProgramStatus;
use App\Models\Trainee;
use App\Models\TrainingProgram;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class UpdateTrainingProgramStatuses extends Command
{
    protected $signature = 'training-programs:update-status';

    protected $description = 'Update training program status based on application dates';
    /**
     * Execute the console command.
     */
    public function handle(): int
    {

        $today = today()->toDateString();

        $this->info("Starting status update. Today: {$today}");
        $this->info('Timezone: ' . config('app.timezone'));
        $this->info('Database: ' . config('database.default'));

        Log::info('Training program status command started', [
            'today' => $today,
            'now' => now()->toDateTimeString(),
            'timezone' => config('app.timezone'),
            'database' => config('database.default'),
        ]);


        $now = now();

        TrainingProgram::where('application_end_date', '>=', $now)
            ->update([
                'status' => TrainingProgramStatus::OPEN->value,
            ]);

        TrainingProgram::where('application_start_date', '>', $now)
            ->orWhere('application_end_date', '<', $now)
            ->update([
                'status' => TrainingProgramStatus::CLOSED->value,
            ]);


        $finishedPrograms = TrainingProgram::whereDate('end_date', '<=', today())
            ->get();

        $this->info("Finished programs found: {$finishedPrograms->count()}");

        foreach ($finishedPrograms as $program) {
            $this->info("Processing program #{$program->id}");

            DB::transaction(function () use ($program) {
                $applications = $program->applications()
                    ->whereHas('trainee')
                    ->where('status', ApplicationStatus::ACTIVE->value)
                    ->with('trainee')
                    ->get();

                $this->info(
                    "Program #{$program->id}: " .
                    "{$applications->count()} active applications with trainees"
                );

                foreach ($applications as $application) {
                    $this->info("Completing application #{$application->id}");

                    $application->update([
                        'status' => ApplicationStatus::COMPLETED->value,
                    ]);

                    $application->trainee?->update([
                        'status' => TraineeStatus::COMPLETED->value,
                    ]);

                    $this->info(
                        "Completed application #{$application->id} " .
                        "and trainee #" . ($application->trainee?->id ?? 'unknown')
                    );
                }
            });
        }
        $this->info('Training program statuses updated.');

        return self::SUCCESS;
    }
}
