<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use App\Enums\ApplicationStatus;
use App\Enums\TraineeStatus;
use App\Enums\TrainingProgramStatus;
use App\Models\TrainingProgram;

class CloseTrainingApplicationsJob implements ShouldQueue
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

        if (now()->toDateString() < $this->program->application_end_date->toDateString()) {
            return;
        }

        $this->program->update([
            'status' => TrainingProgramStatus::CLOSED->value,
        ]);
    }
}
