<?php

namespace App\Jobs;

use App\Models\TrainingProgram;
use App\Services\Training\TrainingProgramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class OpenTrainingApplicationsJob implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public TrainingProgram $program) {}

    public function handle(TrainingProgramService $programs): void
    {
        $programs->refreshRegistration($this->program->id);
    }
}
