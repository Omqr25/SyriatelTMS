<?php

namespace App\Services\Attendance;

use App\Enums\UserRole;
use App\Enums\VacationRequestStatus;
use App\Exceptions\ErrorException;
use App\Helpers\ApiCode;
use App\Models\Absence;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AbsenceService
{
    public function allAbsences(Request $request)
    {
        $user = $request->user();
        $query = Absence::with('trainee.user')->orderByDesc('date')->orderByDesc('id');

        if ($user->trainer) {
            $query->where('trainer_id', $user->trainer->id);
        } elseif ($user->trainee) {
            $query->where('trainee_id', $user->trainee->id);
        } elseif ($user->roles()->where('role', UserRole::TRAINING_TEAM)->exists()) {
            $query->filter($request->validate([
                'name' => ['nullable', 'string', 'max:255'],
                'date' => ['nullable', 'date_format:Y-m-d'],
            ]));
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query->paginate(25)->withQueryString();
    }

    public function recordAbsence(int $traineeId, ?string $reason, User $user): Absence
    {
        try {
            return DB::transaction(function () use ($traineeId, $reason, $user) {
                $trainee = AttendanceEnrollment::lock($traineeId);
                Gate::forUser($user)->authorize('record', [Absence::class, $trainee]);

                $today = today();
                abort_unless(AttendanceEnrollment::isOpen($trainee, $today), 403, __('attendance.inactive_enrollment'));
                $program = $trainee->application->trainingProgram;
                abort_if($today->lt($program->start_date) || $today->isFriday() || $today->isSaturday(),
                    422, __('attendance.invalid_absence_day'));

                $leave = VacationRequest::where('trainee_id', $traineeId)
                    ->where('status', VacationRequestStatus::ACCEPTED)
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                return Absence::create([
                    'trainee_id' => $traineeId,
                    'trainer_id' => $user->trainer->id,
                    'date' => $today,
                    'reason' => $reason,
                    'request_id' => $leave?->id,
                ])->setRelation('trainee', $trainee);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw new ErrorException(__('absence.already_exist'), ApiCode::BAD_REQUEST);
        }
    }
}
