<?php

namespace App\Jobs;

use App\Enums\ApplicationStatus;
use App\Enums\TraineeStatus;
use App\Enums\VacationRequestStatus;
use App\Models\TrainingProgram;
use App\Models\VacationRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class CompleteTraineesJob implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public TrainingProgram $program) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $program = TrainingProgram::lockForUpdate()->find($this->program->id);
            if (! $program || today()->lte($program->end_date)) {
                return;
            }

            VacationRequest::where('status', VacationRequestStatus::PENDING)
                ->whereHas('trainee.application', fn ($query) => $query->where('training_program_id', $program->id))
                ->update(['status' => VacationRequestStatus::EXPIRED]);

            $applications = $program->applications()
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
