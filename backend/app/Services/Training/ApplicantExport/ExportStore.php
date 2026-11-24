<?php

namespace App\Services\Training\ApplicantExport;

use App\Enums\ApplicationStatus;
use App\Models\TrainingProgram;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ExportStore
{
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

    public static function request(TrainingProgram $program, int $userId, string $kind, bool $fresh, ?string $selected): string
    {
        // A single INSERT … SELECT freezes the row values and eligibility in one database statement.
        $columns = Schema::getColumnListing('applications');
        return DB::transaction(function () use ($program, $userId, $kind, $fresh, $selected, $columns) {
            TrainingProgram::whereKey($program->id)->lockForUpdate()->firstOrFail();
            $available = self::exports()->where('program_id', $program->id);
            if ($active = (clone $available)->whereNotNull('operation')->value('id')) {
                return $active;
            }

            $export = $fresh ? null : (clone $available)->where('expires_at', '>', now())
                ->when($selected, fn ($query) => $query->where('id', $selected))
                ->orderByDesc('id')->first();

            abort_if($selected && ! $export && ! $fresh, 404);
            if (! $export) {
                $id = (string) Str::ulid();
                self::exports()->insert([
                    'id' => $id, 'program_id' => $program->id, 'program_name' => $program->name,
                    'created_by' => $userId, 'columns' => json_encode($columns, JSON_THROW_ON_ERROR),
                    'applicant_count' => 0, 'capture_state' => 'queued', 'operation' => 'capture',
                    'next_operation' => $kind === 'capture' ? null : $kind,
                    'excel_state' => 'none', 'documents_state' => 'none',
                    'created_at' => now(), 'updated_at' => now(),
                    'expires_at' => now()->addHours(max(1, config('applicant_exports.retention_hours'))),
                ]);
                $grammar = DB::connection()->getQueryGrammar();
                $fields = implode(', ', array_map(fn ($column) => '? , CAST('.$grammar->wrap($column).' AS CHAR)', $columns));
                $query = DB::table('applications')->where('training_program_id', $program->id)
                    ->whereIn('status', [ApplicationStatus::ACCEPTED, ApplicationStatus::ACTIVE])
                    ->selectRaw("? AS export_id, id, JSON_OBJECT($fields)", [$id, ...$columns])
                    ->orderBy('id')->limit(max(1, config('applicant_exports.max_applicants')) + 1);
                $count = DB::table('applicant_export_rows')->insertUsing(['export_id', 'application_id', 'fields'], $query);
                abort_if($count > config('applicant_exports.max_applicants'), 422, __('applicant_exports.cohort_limit'));
                self::exports()->where('id', $id)->update(['applicant_count' => $count]);
                if ($count === 0) {
                    self::exports()->where('id', $id)->update(['capture_state' => 'ready', 'operation' => null, 'next_operation' => null,
                        'excel_state' => 'empty', 'documents_state' => 'empty']);
                } else {
                    self::task($id, 'capture');
                }

                return $id;
            }

            if ($kind !== 'capture' && $export->capture_state === 'ready' && $export->{$kind.'_state'} === 'none') {
                self::begin($export, $kind);
            }

            return $export->id;
        }, 3);
    }

    public static function begin(object $export, string $kind): void
    {
        self::exports()->where('id', $export->id)->update([
            'operation' => $kind, 'next_operation' => null, $kind.'_state' => 'queued',
        ]);
        self::task($export->id, $kind === 'documents' ? 'plan' : 'excel', estimate: $kind === 'excel'
            ? 1024 ** 2 + $export->applicant_count * 32768 : 0);
    }

    public static function retry(TrainingProgram $program, string $id): string
    {
        return DB::transaction(function () use ($program, $id) {
            TrainingProgram::whereKey($program->id)->lockForUpdate()->firstOrFail();
            $export = self::one($program->id, $id);
            if ($active = self::exports()->where('program_id', $program->id)->whereNotNull('operation')->value('id')) {
                return $active;
            }
            $operation = $export->capture_state === 'failed' || $export->capture_state === 'cancelled' ? 'capture'
                : (in_array($export->excel_state, ['failed', 'cancelled'], true) ? 'excel' : 'documents');
            $kinds = $operation === 'documents' ? ['plan', 'zip'] : [$operation];
            $count = self::tasks()->where('export_id', $id)->whereIn('kind', $kinds)
                ->whereIn('state', ['failed', 'cancelled'])->update([
                    'state' => 'queued', 'due_at' => now(), 'attempts' => 0, 'error' => null, 'dispatch_after' => null,
                ]);
            if ($count) {
                self::exports()->where('id', $id)->update(['operation' => $operation, $operation.'_state' => 'queued']);
            }

            return $id;
        }, 3);
    }

    public static function cancel(TrainingProgram $program, string $id): void
    {
        DB::transaction(function () use ($program, $id) {
            TrainingProgram::whereKey($program->id)->lockForUpdate()->firstOrFail();
            $export = self::exports()->where('program_id', $program->id)->where('id', $id)->lockForUpdate()->first();
            abort_unless($export, 404);
            if ($export->operation) {
                self::tasks()->where('export_id', $id)->whereIn('state', ['queued', 'running'])->update(['state' => 'cancelled']);
                // Keep the program slot until running tasks release their leases.
                self::exports()->where('id', $id)->update([$export->operation.'_state' => 'cancelled', 'next_operation' => null]);
                self::settle($id);
            }
        }, 3);
    }

    public static function settle(string $id): void
    {
        $export = self::exports()->where('id', $id)->lockForUpdate()->first();
        if (! $export?->operation || self::tasks()->where('export_id', $id)->where(fn ($query) =>
            $query->whereIn('state', ['queued', 'running'])->orWhereNotNull('owner'))->exists()) {
            return;
        }
        $operation = $export->operation;
        $state = $export->{$operation.'_state'};
        $kinds = $operation === 'documents' ? ['plan', 'zip'] : [$operation];
        if (! in_array($state, ['cancelled', 'empty'], true)) {
            $state = self::tasks()->where('export_id', $id)->whereIn('kind', $kinds)->where('state', 'failed')->exists()
                ? 'failed' : 'ready';
        }
        self::exports()->where('id', $id)->update([$operation.'_state' => $state, 'operation' => null]);
        if ($operation === 'capture' && $state === 'ready' && $export->next_operation && now()->lt($export->expires_at)) {
            self::begin($export, $export->next_operation);
        }
    }

    public static function one(int $program, string $id): object
    {
        $export = self::exports()->where('program_id', $program)->where('id', $id)->where('expires_at', '>', now())->first();
        abort_unless($export, 404);

        return $export;
    }
}
