<?php

namespace App\Services\Training;

use App\Enums\TrainingProgramStatus;
use App\Exceptions\ErrorException;
use App\Helpers\ApiCode;
use App\Jobs\CloseTrainingApplicationsJob;
use App\Jobs\CompleteTraineesJob;
use App\Jobs\OpenTrainingApplicationsJob;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrainingProgramService
{
    private const DATES = ['application_start_date', 'application_end_date', 'start_date', 'end_date'];

    private const LINKS = ['applications', 'programs', 'certificates', 'publishedApplicationForm'];

    public function __construct(private ProgramDateChangeNotifier $dateChanges) {}

    public function upcomingTrainingProgram()
    {
        $today = today()->toDateString();
        $training_program = TrainingProgram::whereNull('archived_at')
            ->where('application_start_date', '<=', $today)
            ->where('application_end_date', '>=', $today)
            ->orderBy('application_start_date', 'desc')
            ->first();
        if (! $training_program) {
            $training_program = TrainingProgram::whereNull('archived_at')
                ->where('application_start_date', '>', $today)
                ->where('application_end_date', '>=', $today)
                ->orderBy('application_start_date', 'asc')
                ->first();
        }
        if (! $training_program) {
            $training_program = TrainingProgram::whereNull('archived_at')
                ->whereDate('end_date', '>=', $today)
                ->orderBy('start_date', 'asc')
                ->first();
        }

        return $training_program;
    }

    public function create(array $data, User $actor): TrainingProgram
    {
        return DB::transaction(function () use ($data, $actor) {
            $this->lockCalendar();
            $program = new TrainingProgram(Arr::except($data, ['draft']));
            $program->archived_at = ! empty($data['draft']) ? now() : null;
            $program->description ??= '';
            $this->validateCalendar($program);
            $this->applyRegistrationStatus($program);
            $program->save();
            $this->audit($program, $actor, 'create', null, $this->snapshot($program));
            if (! $program->archived_at) {
                $this->scheduleTransitions($program, self::DATES);
            }

            return $program;
        }, 3);
    }

    public function update(int $id, array $data, User $actor): TrainingProgram
    {
        return DB::transaction(function () use ($id, $data, $actor) {
            $this->lockCalendar();
            $program = TrainingProgram::lockForUpdate()->findOrFail($id);
            $this->checkRevision($program, $data['expected_revision'] ?? null);
            $before = $this->snapshot($program);
            // Latch the old schedule before replacing it, even if no worker ran.
            $this->applyRegistrationStatus($program);
            $program->fill(Arr::only($data, $program->getFillable()));
            $program->description ??= '';
            $this->validateCalendar($program);
            $changedDates = array_intersect(self::DATES, array_keys($program->getDirty()));
            if ($changedDates && $program->applications()->exists() && empty($data['confirm_date_change'])) {
                throw new ErrorException(__('training_program.confirm_dates'), 409, ['reason' => 'confirm_dates']);
            }

            $this->applyRegistrationStatus($program);
            if ($program->isDirty()) {
                $program->revision++;
                $program->save();
                $this->audit($program, $actor, 'update', $before, $this->snapshot($program));
                $this->scheduleTransitions($program, $changedDates);
                if ($changedDates) {
                    $this->dateChanges->notify($program, $actor, $this->snapshot($program));
                }
            }

            return $program->loadExists(self::LINKS);
        }, 3);
    }

    public function setArchived(int $id, bool $archive, User $actor, ?int $revision): TrainingProgram
    {
        return DB::transaction(function () use ($id, $archive, $actor, $revision) {
            $this->lockCalendar();
            $program = TrainingProgram::lockForUpdate()->findOrFail($id);
            $this->checkRevision($program, $revision);
            if ((bool) $program->archived_at !== $archive) {
                $before = $this->snapshot($program);
                $this->applyRegistrationStatus($program);
                $program->archived_at = $archive ? now() : null;
                if (! $archive) {
                    $this->validateCalendar($program);
                }
                $this->applyRegistrationStatus($program);
                $program->revision++;
                $program->save();
                $this->audit($program, $actor, $archive ? 'archive' : 'restore', $before, $this->snapshot($program));
                if (! $archive) {
                    $this->scheduleTransitions($program, self::DATES);
                }
            }

            return $program->loadExists(self::LINKS);
        }, 3);
    }

    public function delete(int $id, User $actor, ?int $expectedRevision = null): void
    {
        DB::transaction(function () use ($id, $actor, $expectedRevision) {
            $this->lockCalendar();
            $program = TrainingProgram::lockForUpdate()->findOrFail($id);
            $this->checkRevision($program, $expectedRevision);
            $block = $program->loadExists(self::LINKS)->deletionBlock();
            if ($block) {
                throw new ErrorException(__("training_program.delete_{$block}"), 409, ['reason' => $block]);
            }
            $this->audit($program, $actor, 'delete', $this->snapshot($program), null);
            $program->applicationForm()->whereNull('published_at')->delete();
            $program->delete();
        }, 3);
    }

    private function lockCalendar(): void
    {
        // An actual write takes a transaction lock on SQLite as well as MySQL/Postgres.
        DB::table('training_program_calendar')->where('id', 1)->increment('revision');
    }

    private function checkRevision(TrainingProgram $program, ?int $expected): void
    {
        if ($expected !== null && $expected !== $program->revision) {
            throw new ErrorException(__('training_program.stale'), 409, ['reason' => 'stale']);
        }
    }

    private function validateCalendar(TrainingProgram $program): void
    {
        $dates = Arr::only($this->snapshot($program), self::DATES);
        Validator::make($dates, [
            'application_end_date' => 'after_or_equal:application_start_date',
            'start_date' => 'after:application_end_date',
            'end_date' => 'after_or_equal:start_date',
        ])->validate();

        $others = TrainingProgram::query()->when($program->exists, fn ($q) => $q->whereKeyNot($program->id));
        $samePeriod = (clone $others)->whereDate('start_date', $dates['start_date'])
            ->whereDate('end_date', $dates['end_date'])->pluck('name');
        $normalize = fn ($name) => Str::lower(Str::squish($name));
        if ($samePeriod->contains(fn ($name) => $normalize($name) === $normalize($program->name))) {
            throw ValidationException::withMessages(['name' => __('training_program.duplicate')]);
        }
        if ($program->archived_at) {
            return;
        }
        $others->whereNull('archived_at');
        foreach ([['application_start_date', 'application_end_date'], ['start_date', 'end_date']] as [$start, $end]) {
            if ((clone $others)->whereDate($start, '<=', $dates[$end])->whereDate($end, '>=', $dates[$start])->exists()) {
                throw ValidationException::withMessages([$start => __('training_program.overlap')]);
            }
        }
    }

    private function applyRegistrationStatus(TrainingProgram $program): void
    {
        if ($program->status === TrainingProgramStatus::OPEN
            || (! $program->archived_at && today()->gte($program->application_start_date))) {
            $program->registration_has_opened = true;
        }
        $program->status = $program->registrationStatus();
    }

    public function refreshRegistration(int $id): void
    {
        DB::transaction(function () use ($id) {
            $program = TrainingProgram::lockForUpdate()->find($id);
            if ($program) {
                $this->applyRegistrationStatus($program);
                if ($program->isDirty()) {
                    $program->save();
                }
            }
        });
    }

    private function snapshot(TrainingProgram $program): array
    {
        $fields = $program->only(['name', 'description', 'registration_has_opened', 'revision']);
        $fields['archived_at'] = $program->archived_at?->toIso8601String();
        foreach (self::DATES as $date) {
            $fields[$date] = $program->$date->toDateString();
        }

        return $fields;
    }

    private function audit(TrainingProgram $program, User $actor, string $action, ?array $before, ?array $after): void
    {
        DB::table('training_program_audits')->insert([
            'training_program_id' => $program->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'before_values' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_values' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    private function scheduleTransitions(TrainingProgram $program, array $changedDates): void
    {
        if (in_array('application_start_date', $changedDates) && $program->application_start_date->isFuture()) {
            OpenTrainingApplicationsJob::dispatch($program)->delay($program->application_start_date)->afterCommit();
        }
        $close = $program->application_end_date->copy()->addDay()->startOfDay();
        if (in_array('application_end_date', $changedDates) && $close->isFuture()) {
            CloseTrainingApplicationsJob::dispatch($program)->delay($close)->afterCommit();
        }
        if (in_array('end_date', $changedDates)) {
            CompleteTraineesJob::dispatch($program)->delay($program->end_date->copy()->addDay()->startOfDay())->afterCommit();
        }
    }

    public function findAll(bool $withManagement = false, bool $includeArchived = false)
    {
        return TrainingProgram::query()->when(! $includeArchived, fn ($q) => $q->whereNull('archived_at'))
            ->when($withManagement, fn ($q) => $q->withExists(self::LINKS))
            ->latest()->orderByDesc('id')->get();
    }

    public function find(int $training_program_id): TrainingProgram
    {
        $trainingProgram = TrainingProgram::whereNull('archived_at')->find($training_program_id);
        if (! $trainingProgram) {
            throw new ErrorException(__('training_program.not_found'), ApiCode::NOT_FOUND);
        }

        return $trainingProgram;
    }

    public function checkProgramStatus(int $id): TrainingProgramStatus
    {
        return $this->find($id)->registrationStatus();
    }

    public function openForRegistration(): ?TrainingProgram
    {
        return TrainingProgram::whereNull('archived_at')
            ->whereDate('application_start_date', '<=', today())->whereDate('application_end_date', '>=', today())
            ->latest()->first();
    }

    public function openProgram(): ?TrainingProgram
    {
        return TrainingProgram::whereNull('archived_at')->where('status', TrainingProgramStatus::OPEN)->latest()->first();
    }
}
