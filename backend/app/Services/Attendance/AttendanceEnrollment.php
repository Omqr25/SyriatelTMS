<?php

namespace App\Services\Attendance;

use App\Enums\ApplicationStatus;
use App\Enums\TraineeStatus;
use App\Models\Application;
use App\Models\Trainee;
use App\Models\TrainingProgram;
use Illuminate\Support\Carbon;

class AttendanceEnrollment
{
    // Call inside a transaction. Take the program lock first, as the completion job does.
    public static function lock(int $traineeId): Trainee
    {
        $trainee = Trainee::with('application')->findOrFail($traineeId);
        $programId = $trainee->application?->training_program_id;
        $program = $programId ? TrainingProgram::sharedLock()->find($programId) : null;

        $trainee = Trainee::lockForUpdate()->findOrFail($traineeId);
        $application = $trainee->application_id ? Application::lockForUpdate()->find($trainee->application_id) : null;
        abort_if($application?->training_program_id !== $programId, 409, __('attendance.enrollment_changed'));

        $application?->setRelation('trainingProgram', $program);
        $trainee->setRelation('application', $application);

        return $trainee;
    }

    public static function isOpen(Trainee $trainee, Carbon $day): bool
    {
        $program = $trainee->application?->trainingProgram;

        return $trainee->status === TraineeStatus::ACTIVE
            && $trainee->application?->status === ApplicationStatus::ACTIVE
            && $program?->start_date !== null
            && $program->end_date !== null
            && $program->start_date->lte($program->end_date)
            && $day->lte($program->end_date);
    }
}
