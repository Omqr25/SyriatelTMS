<?php

namespace App\Services\Training\ApplicantExport;

use App\Enums\ApplicationStatus;
use App\Models\TrainingProgram;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExportStore
{
    public const EXCEL_FIELDS = [
        'id', 'national_id_number', 'training_program_id', 'full_name', 'email', 'phone_number',
        'birth_date', 'city', 'home_address', 'university', 'field_of_study', 'academic_major',
        'year_of_study', 'gpa', 'technical_skills', 'commitment', 'previous_training', 'how_heard',
        'additional_info', 'status', 'created_at', 'updated_at', 'english_test_score', 'application_form_id',
    ];

    public const DOCUMENTS = [
        'cv' => 'CV', 'national_id' => 'National_ID',
        'academic_qualification' => 'Academic_Qualification', 'personal_photo' => 'Personal_Photo',
    ];

    public static function exports()
    {
        return DB::table('applicant_exports');
    }

    public static function rows(string $id)
    {
        return DB::table('applicant_export_rows')->where('export_id', $id);
    }

    public static function tasks()
    {
        return DB::table('applicant_export_tasks');
    }

    public static function task(string $id, string $kind, int $part = 0, int $estimate = 0): void
    {
        self::tasks()->insert([
            'export_id' => $id, 'kind' => $kind, 'part' => $part, 'estimate' => $estimate,
            'state' => 'queued', 'due_at' => now(),
        ]);
    }

    public static function request(TrainingProgram $program, int $userId, string $kind, bool $fresh, ?string $selected, ?string $requestKey = null): string
    {
        return DB::transaction(function () use ($program, $userId, $kind, $fresh, $selected, $requestKey) {
            $program = TrainingProgram::whereKey($program->id)->lockForUpdate()->firstOrFail();
            $available = self::exports()->where('program_id', $program->id)->where('expires_at', '>', now());
            if ($fresh && $requestKey) {
                $existing = self::exports()->where('program_id', $program->id)->where('request_key', $requestKey)->first();
                if ($existing) {
                    abort_if(now()->gte($existing->expires_at), 410);
                    return $existing->id;
                }
            }
            $export = $selected ? self::one($program->id, $selected, true)
                : ($fresh ? null : (clone $available)->orderByDesc('id')->first());
            if (! $export) {
                $id = (string) Str::ulid();
                self::exports()->insert([
                    'id' => $id, 'program_id' => $program->id, 'program_name' => $program->name,
                    'created_by' => $userId, 'request_key' => $fresh ? $requestKey : null, 'columns' => json_encode(self::EXCEL_FIELDS, JSON_THROW_ON_ERROR),
                    'applicant_count' => 0, 'capture_state' => 'queued',
                    'excel_state' => 'none', 'documents_state' => 'none',
                    'created_at' => now(), 'updated_at' => now(),
                    'expires_at' => now()->addHours(max(1, config('applicant_exports.retention_hours'))),
                ]);
                $columns = [...self::EXCEL_FIELDS, ...array_keys(self::DOCUMENTS)];
                $grammar = DB::connection()->getQueryGrammar();
                $fields = implode(', ', array_map(fn ($column) => '? , CAST('.$grammar->wrap($column).' AS CHAR)', $columns));
                $limit = max(1, config('applicant_exports.max_applicants'));
                // One statement freezes both the eligibility and the original document references.
                $query = DB::table('applications')->where('training_program_id', $program->id)
                    ->whereIn('status', [ApplicationStatus::ACCEPTED, ApplicationStatus::ACTIVE])
                    ->selectRaw("? AS export_id, id, JSON_OBJECT($fields)", [$id, ...$columns])
                    ->orderBy('id')->limit($limit + 1);
                $count = DB::table('applicant_export_rows')->insertUsing(['export_id', 'application_id', 'fields'], $query);
                abort_if($count > $limit, 422, __('applicant_exports.cohort_limit'));
                self::exports()->where('id', $id)->update(['applicant_count' => $count]);
                if ($count === 0) {
                    self::exports()->where('id', $id)->update(['capture_state' => 'ready', 'excel_state' => 'empty', 'documents_state' => 'empty']);
                } else {
                    self::task($id, 'capture');
                }
                $export = self::exports()->find($id);
            } elseif (! $selected) {
                $export = self::one($program->id, $export->id, true);
            }

            if ($kind !== 'capture' && $export->{$kind.'_state'} === 'none') {
                if ($kind === 'documents') self::ensureDocumentSlot($export);
                self::begin($export, $kind);
            }

            return $export->id;
        }, 3);
    }

    public static function begin(object $export, string $kind): void
    {
        self::exports()->where('id', $export->id)->update([$kind.'_state' => 'queued']);
        if ($kind === 'excel') {
            self::task($export->id, 'excel', estimate: 1024 ** 2 + $export->applicant_count * 32768);
        } elseif ($export->capture_state === 'ready') {
            self::task($export->id, 'plan');
        } elseif (in_array($export->capture_state, ['failed', 'cancelled'], true)) {
            self::reset($export->id, ['capture']);
            self::exports()->where('id', $export->id)->update(['capture_state' => 'queued']);
        }
    }

    private static function ensureDocumentSlot(object $export): void
    {
        abort_if(self::exports()->where('program_id', $export->program_id)->where('id', '!=', $export->id)
            ->where('expires_at', '>', now())->where('documents_state', 'queued')->exists(), 409,
            __('applicant_exports.document_busy'));
    }

    private static function kinds(string $kind): array
    {
        return $kind === 'excel' ? ['excel'] : ['capture', 'plan', 'zip'];
    }

    private static function reset(string $id, array $kinds): int
    {
        return self::tasks()->where('export_id', $id)->whereIn('kind', $kinds)
            ->whereIn('state', ['failed', 'cancelled'])->whereNull('owner')->update([
                'state' => 'queued', 'due_at' => now(), 'attempts' => 0, 'error' => null, 'dispatch_after' => null,
                'lock_failures' => 0, 'capacity_required' => null, 'capacity_available' => null,
            ]);
    }

    public static function retry(TrainingProgram $program, string $id, string $kind): string
    {
        return DB::transaction(function () use ($program, $id, $kind) {
            TrainingProgram::whereKey($program->id)->lockForUpdate()->firstOrFail();
            $export = self::one($program->id, $id, true);
            $preparationOnly = $kind === 'documents' && $export->documents_state === 'none';
            $state = $preparationOnly ? $export->capture_state : $export->{$kind.'_state'};
            if (! in_array($state, ['failed', 'cancelled'], true)) return $id;
            if ($kind === 'documents' && ! $preparationOnly) self::ensureDocumentSlot($export);
            $kinds = $preparationOnly ? ['capture'] : self::kinds($kind);
            abort_if(self::tasks()->where('export_id', $id)->whereIn('kind', $kinds)->whereNotNull('owner')->exists(),
                409, __('applicant_exports.cancelling'));
            $reset = self::reset($id, $kinds);
            if ($kind === 'documents' && ! $preparationOnly && $export->capture_state === 'ready'
                && ! self::tasks()->where('export_id', $id)->where('kind', 'plan')->exists()) {
                self::task($id, 'plan');
                $reset++;
            }
            if ($reset) {
                if (! $preparationOnly) self::exports()->where('id', $id)->update([$kind.'_state' => 'queued']);
                self::settle($id, $kind === 'documents' ? 'capture' : 'excel');
            }

            return $id;
        }, 3);
    }

    public static function cancel(TrainingProgram $program, string $id, string $kind): void
    {
        DB::transaction(function () use ($program, $id, $kind) {
            TrainingProgram::whereKey($program->id)->lockForUpdate()->firstOrFail();
            $export = self::one($program->id, $id, true);
            if ($export->{$kind.'_state'} !== 'queued' && ! ($kind === 'documents' && $export->capture_state === 'queued')) return;

            $tasks = self::tasks()->where('export_id', $id)->whereIn('kind', self::kinds($kind))
                ->whereIn('state', ['queued', 'running']);
            (clone $tasks)->whereNull('owner')->where('attempts', 0)->update(['reservation' => 0]);
            $tasks->update(['state' => 'cancelled']);
            if ($export->{$kind.'_state'} === 'queued') {
                self::exports()->where('id', $id)->update([$kind.'_state' => 'cancelled']);
            }
            self::settle($id, $kind === 'documents' ? 'capture' : 'excel');
        }, 3);
    }

    public static function settle(string $id, string $kind): void
    {
        if ($kind === 'prune') return;
        $export = self::exports()->where('id', $id)->lockForUpdate()->first();
        if (! $export) return;
        $tasks = self::tasks()->where('export_id', $id);

        if ($kind === 'capture') {
            $capture = (clone $tasks)->where('kind', 'capture')->value('state');
            if ($capture) self::exports()->where('id', $id)->update(['capture_state' => $capture === 'running' ? 'queued' : $capture]);
            if ($export->documents_state !== 'queued') return;
            if (in_array($capture, ['failed', 'cancelled'], true)) {
                self::exports()->where('id', $id)->update(['documents_state' => $capture]);
            } elseif ($capture === 'ready' && now()->lt($export->expires_at)
                && ! (clone $tasks)->where('kind', 'plan')->exists()) {
                self::task($id, 'plan');
            }

            return;
        }

        $lane = $kind === 'excel' ? 'excel' : 'documents';
        if ($export->{$lane.'_state'} !== 'queued') return;
        $states = $tasks->whereIn('kind', self::kinds($lane))->pluck('state');
        if ($states->intersect(['queued', 'running'])->isNotEmpty()) return;
        $state = $states->contains('failed') ? 'failed' : ($states->contains('cancelled') ? 'cancelled' : 'ready');
        self::exports()->where('id', $id)->update([$lane.'_state' => $state]);
    }

    public static function one(int $program, string $id, bool $lock = false): object
    {
        $export = self::exports()->where('program_id', $program)->where('id', $id)->where('expires_at', '>', now())
            ->when($lock, fn ($query) => $query->lockForUpdate())->first();
        abort_unless($export, 404);

        return $export;
    }
}
