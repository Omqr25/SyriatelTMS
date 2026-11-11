<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use App\Enums\ApplicationStatus;
use App\Enums\TraineeStatus;
use App\Enums\TrainingProgramStatus;
use App\Models\TrainingProgram;
use Illuminate\Support\Facades\DB;

class CompleteTraineesJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public TrainingProgram $program
    ) {}

    /**
     * Execute the job.
     */


    public function handle(): void
    {
        $this->program->refresh();

        if (now()->toDateString() < $this->program->end_date->toDateString()) {
            return;
        }

        DB::transaction(function () {
            $applications = $this->program->applications()
                ->where('status', ApplicationStatus::ACTIVE->value)
                ->whereHas('trainee')
                ->with('trainee')
                ->get();

            foreach ($applications as $application) {
                $application->update([
                    'status' => ApplicationStatus::COMPLETED->value,
                ]);

                $application->trainee?->update([
                    'status' => TraineeStatus::COMPLETED->value,
                ]);
            }
        });
    }
}
