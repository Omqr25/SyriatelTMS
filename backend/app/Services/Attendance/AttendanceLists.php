<?php

namespace App\Services\Attendance;

use App\Enums\TraineeStatus;
use App\Models\Trainee;
use App\Models\Trainer;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceLists
{
    public static function filters(Request $request, array $sorts, array $fields = []): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'trainee_id' => ['nullable', 'integer', 'min:1'],
            'program_id' => ['nullable', 'integer', 'min:1'],
            'supervisor_id' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', Rule::in($sorts)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ] + $fields);
    }

    public static function filterTrainees(Builder $query, User $user, array $filters): Builder
    {
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search) {
                $query->whereHas('user', fn (Builder $person) => $person
                    ->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                if (ctype_digit($search) && strlen($search) <= 18) {
                    $query->orWhere('trainees.id', $search);
                }
            });
        }

        return $query
            ->when($filters['trainee_id'] ?? null, fn (Builder $q, $id) => $q->where('trainees.id', $id))
            ->when($filters['program_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('application', fn (Builder $a) => $a->where('training_program_id', $id)))
            ->when(AttendanceAccess::isHr($user) ? ($filters['supervisor_id'] ?? null) : null,
                fn (Builder $q, $id) => $q->where('trainees.trainer_id', $id));
    }

    public static function sort(Builder $query, array $filters, string $defaultSort, string $defaultDirection = 'desc'): Builder
    {
        $key = $filters['sort'] ?? $defaultSort;
        $direction = $filters['direction'] ?? $defaultDirection;
        if ($key === 'name') {
            if ($query->getModel() instanceof Trainee) {
                $name = User::select('name')->whereColumn('users.id', 'trainees.user_id');
            } else {
                $name = Trainee::select('users.name')->join('users', 'users.id', '=', 'trainees.user_id')
                    ->whereColumn('trainees.id', $query->getModel()->qualifyColumn('trainee_id'));
            }
            $query->orderBy($name, $direction);
        } else {
            $query->orderBy($key, $direction);
        }

        return $query->orderBy($query->getModel()->getQualifiedKeyName(), $direction);
    }

    public static function withCounts(Builder $query): Builder
    {
        return $query->withCount([
            'absences as total_absences',
            'absences as excused_absences' => fn (Builder $q) => $q->classification(true),
            'absences as unexcused_absences' => fn (Builder $q) => $q->classification(false),
        ]);
    }

    public function records(Request $request)
    {
        $user = $request->user('api');
        $filters = self::filters($request, ['name', 'total_absences', 'excused_absences', 'unexcused_absences'], [
            'recorded' => ['nullable', Rule::in(['with', 'without', 'unexcused'])],
        ]);
        $query = self::filterTrainees(AttendanceAccess::trainees($user), $user, $filters)
            ->where('trainees.status', TraineeStatus::ACTIVE)
            ->with('user:id,name,email');
        match ($filters['recorded'] ?? null) {
            'with' => $query->has('absences'),
            'without' => $query->doesntHave('absences'),
            'unexcused' => $query->whereHas('absences', fn (Builder $q) => $q->classification(false)),
            default => null,
        };

        return self::sort(self::withCounts($query), $filters, 'name', 'asc')->paginate(25)->withQueryString();
    }

    public function record(User $user, int $id): Trainee
    {
        return self::withCounts(AttendanceAccess::trainees($user))
            ->with(['user:id,name,email', 'trainer.user:id,name', 'department:id,name', 'application.trainingProgram:id,name'])
            ->findOrFail($id);
    }

    public static function counts(Trainee $trainee): array
    {
        return [
            'total_absences' => (int) $trainee->total_absences,
            'excused_absences' => (int) $trainee->excused_absences,
            'unexcused_absences' => (int) $trainee->unexcused_absences,
        ];
    }

    public function options(User $user): array
    {
        $applications = AttendanceAccess::trainees($user)->whereNotNull('trainees.application_id')->select('trainees.application_id');
        $programs = TrainingProgram::whereHas('applications', fn (Builder $q) => $q->whereIn('applications.id', $applications))
            ->orderBy('name')->get(['id', 'name']);
        $supervisors = AttendanceAccess::isHr($user)
            ? Trainer::has('trainees')->with('user:id,name')->orderBy('id')->get(['id', 'user_id'])
                ->map(fn (Trainer $trainer) => ['id' => $trainer->id, 'name' => $trainer->user?->name])
            : [];

        return ['programs' => $programs, 'supervisors' => $supervisors];
    }
}
