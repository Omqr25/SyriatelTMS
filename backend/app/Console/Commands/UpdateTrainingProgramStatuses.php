<?php

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Enums\TrainingProgramStatus;
use App\Jobs\CompleteTraineesJob;
use App\Models\TrainingProgram;
use App\Services\Training\TrainingProgramService;
use Illuminate\Console\Command;

class UpdateTrainingProgramStatuses extends Command
{
    protected $signature = 'training-programs:update-status';

    protected $description = 'Apply registration window transitions and complete finished trainees';

    public function handle(TrainingProgramService $programs): int
    {
        $today = today()->toDateString();
        TrainingProgram::query()->where(function ($q) use ($today) {
            $q->where(fn ($q) => $q->whereNull('archived_at')->whereDate('application_start_date', '<=', $today)
                ->where(fn ($q) => $q->whereNull('registration_has_opened')->orWhere('registration_has_opened', false)))
                ->orWhere(fn ($q) => $q->whereNull('archived_at')->where('status', TrainingProgramStatus::CLOSED)
                    ->whereDate('application_start_date', '<=', $today)->whereDate('application_end_date', '>=', $today))
                ->orWhere(fn ($q) => $q->where('status', TrainingProgramStatus::OPEN)
                    ->where(fn ($q) => $q->whereDate('application_start_date', '>', $today)
                        ->orWhereDate('application_end_date', '<', $today)));
        })->select('id')->eachById(fn ($p) => $programs->refreshRegistration($p->id));

        TrainingProgram::whereDate('end_date', '<', $today)
            ->whereHas('applications', fn ($q) => $q->where('status', ApplicationStatus::ACTIVE)->whereHas('trainee'))
            ->eachById(fn ($p) => (new CompleteTraineesJob($p))->handle());

        return self::SUCCESS;
    }
}
