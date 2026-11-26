<?php

namespace App\Services\Attendance;

use App\Enums\ApplicationStatus;
use App\Enums\TraineeStatus;
use App\Exceptions\ErrorException;
use App\Helpers\ApiCode;
use App\Models\Absence;
use App\Models\Trainee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AbsenceService
{
    public function allAbsences(Request $request, bool $legacy = false)
    {
        $user = $request->user('api')->load('roles');
        $filters = AttendanceLists::filters($request, ['date', 'name', 'is_excused'], [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'classification' => ['nullable', Rule::in(['excused', 'unexcused'])],
            'name' => ['nullable', 'string', 'max:255'],
        ]);
        if ($legacy && AttendanceAccess::isHr($user)) {
            $filters['date'] ??= today()->toDateString();
            $filters['search'] ??= $filters['name'] ?? '';
        }

        $trainees = AttendanceLists::filterTrainees(AttendanceAccess::trainees($user), $user, $filters);
        $query = Absence::withClassification()->with('trainee.user')
            ->whereIn('absences.trainee_id', $trainees->select('trainees.id'))
            ->when($filters['date'] ?? null, fn (Builder $q, $date) => $q->whereDate('date', $date))
            ->when($filters['classification'] ?? null, fn (Builder $q, $value) => $q->classification($value === 'excused'));

        return AttendanceLists::sort($query, $filters, 'date')->paginate(25)->withQueryString();
    }

    public function eligibleTrainees(User $user)
    {
        $trainerId = AttendanceAccess::supervisorId($user);
        abort_unless($trainerId, 403);

        $today = today();
        if (! $this->isWorkingDay($today)) {
            return collect();
        }

        return Trainee::query()
            ->where('trainer_id', $trainerId)
            ->where('status', TraineeStatus::ACTIVE)
            ->whereHas('application', fn ($application) => $application
                ->where('status', ApplicationStatus::ACTIVE)
                ->whereHas('trainingProgram', fn ($program) => $program
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today)))
            ->with('user:id,name')
            ->orderBy('id')
            ->get(['id', 'user_id'])
            ->map(fn (Trainee $trainee) => ['id' => $trainee->id, 'name' => $trainee->user->name]);
    }

    private function isWorkingDay(Carbon $day): bool
    {
        return ! $day->isFriday() && ! $day->isSaturday();
    }

    public function recordAbsence(int $traineeId, string $reason, User $user): Absence
    {
        try {
            $absence = DB::transaction(function () use ($traineeId, $reason, $user) {
                $trainee = AttendanceEnrollment::lock($traineeId);
                Gate::forUser($user)->authorize('record', [Absence::class, $trainee]);

                $today = today();
                abort_unless(AttendanceEnrollment::isOpen($trainee, $today), 403, __('attendance.inactive_enrollment'));
                $program = $trainee->application->trainingProgram;
                abort_if($today->lt($program->start_date) || ! $this->isWorkingDay($today),
                    422, __('attendance.invalid_absence_day'));

                // Legacy duplicate rows remain visible. A non-canonical row still prevents another record.
                $existing = Absence::where('trainee_id', $traineeId)->whereDate('date', $today)
                    ->lockForUpdate()->first();
                if ($existing) {
                    throw new ErrorException(__('absence.already_exist'), ApiCode::BAD_REQUEST);
                }

                return Absence::create([
                    'trainee_id' => $traineeId,
                    'trainer_id' => $user->trainer->id,
                    'date' => $today,
                    'reason' => $reason,
                ])->setRelation('trainee', $trainee);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw new ErrorException(__('absence.already_exist'), ApiCode::BAD_REQUEST);
        }

        return $this->classified($absence);
    }

    public function update(int $id, User $user, string $reason): Absence
    {
        $absence = $this->mutate($id, $user, 'update', function (Absence $absence) use ($reason) {
            $absence->update(['reason' => $reason]);

            return $absence;
        });

        return $this->classified($absence);
    }

    private function classified(Absence $absence): Absence
    {
        return Absence::withTrashed()->withClassification()->findOrFail($absence->id)
            ->setRelation('trainee', $absence->trainee);
    }

    public function delete(int $id, User $user): void
    {
        $this->mutate($id, $user, 'delete', function (Absence $absence) {
            $absence->forceFill(['is_canonical' => null])->save();
            $absence->delete();
        });
    }

    private function mutate(int $id, User $user, string $ability, callable $change): mixed
    {
        $absence = Absence::findOrFail($id);
        Gate::forUser($user)->authorize($ability, $absence);

        return DB::transaction(function () use ($absence, $user, $ability, $change) {
            $trainee = AttendanceEnrollment::lock($absence->trainee_id);
            $record = Absence::lockForUpdate()->findOrFail($absence->id)->setRelation('trainee', $trainee);
            Gate::forUser($user)->authorize($ability, $record);

            return $change($record);
        }, 3);
    }
}
