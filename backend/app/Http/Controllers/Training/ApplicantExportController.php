<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\TrainingProgram;
use App\Services\Training\ApplicantExport\ExportFiles;
use App\Services\Training\ApplicantExport\ExportQueue;
use App\Services\Training\ApplicantExport\ExportReport;
use App\Services\Training\ApplicantExport\ExportStore;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ApplicantExportController extends Controller
{
    public function index(Request $request, TrainingProgram $program)
    {
        $filters = $request->validate(['before' => ['sometimes', 'ulid'], 'selected' => ['sometimes', 'ulid']]);
        $history = ExportStore::exports()->where('program_id', $program->id)->where('expires_at', '>', now())
            ->when($filters['before'] ?? null, fn ($query, $before) => $query->where('id', '<', $before))
            ->orderByDesc('id')->limit(21)->get();
        $snapshots = $history->take(20)->values();
        $next = $history->count() > 20 ? $snapshots->last()->id : null;
        if (isset($filters['selected']) && ! $snapshots->contains('id', $filters['selected'])) {
            $selected = ExportStore::exports()->where('program_id', $program->id)->where('id', $filters['selected'])->first();
            if ($selected) $snapshots->push($selected);
        }
        $tasks = ExportStore::tasks()->whereIn('export_id', $snapshots->pluck('id'))->where('kind', '!=', 'prune')
            ->orderBy('part')->get(['id', 'export_id', 'kind', 'part', 'state', 'estimate', 'progress', 'bytes', 'sha256', 'error', 'capacity_required', 'capacity_available'])
            ->groupBy('export_id');
        $result = $snapshots->map(function ($snapshot) use ($tasks) {
            foreach (['created_at', 'updated_at', 'expires_at'] as $attribute) {
                $snapshot->{$attribute} = Carbon::parse($snapshot->{$attribute})->toIso8601String();
            }
            $snapshot->expired = now()->gte($snapshot->expires_at);
            $snapshot->data_state = $snapshot->expired ? 'expired' : 'ready';
            if ($snapshot->capture_state !== 'ready') $snapshot->document_warnings = null;
            unset($snapshot->columns, $snapshot->next_operation, $snapshot->operation, $snapshot->created_by, $snapshot->request_key);
            $snapshot->tasks = ($tasks[$snapshot->id] ?? collect())->map(function ($task) {
                $task->oversized = $task->kind === 'zip' && $task->estimate > config('applicant_exports.part_bytes');
                unset($task->estimate, $task->export_id);
                return $task;
            });

            return $snapshot;
        });

        return response()->json(['data' => $result, 'next_before' => $next, 'retention_hours' => max(1, config('applicant_exports.retention_hours'))])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, TrainingProgram $program, ExportQueue $queue)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['capture', 'excel', 'documents'])],
            'fresh' => ['sometimes', 'boolean'], 'snapshot_id' => ['sometimes', 'nullable', 'ulid', 'prohibited_if:fresh,true'],
            'request_key' => ['sometimes', 'uuid'],
        ]);
        $id = ExportStore::request($program, $request->user()->id, $data['kind'], $request->boolean('fresh'), $data['snapshot_id'] ?? null, $data['request_key'] ?? null);
        Log::info('Applicant export requested', ['snapshot_id' => $id, 'program_id' => $program->id, 'user_id' => $request->user()->id, 'kind' => $data['kind']]);
        $queue->dispatch($id);

        return response()->json(['id' => $id], 202)->header('Cache-Control', 'private, no-store');
    }

    public function retry(Request $request, TrainingProgram $program, string $snapshot, ExportQueue $queue)
    {
        $kind = $request->validate(['kind' => ['required', Rule::in(['excel', 'documents'])]])['kind'];
        $id = ExportStore::retry($program, $snapshot, $kind);
        Log::info('Applicant export retry requested', ['snapshot_id' => $id, 'user_id' => $request->user()->id]);
        $queue->dispatch($id);

        return response()->json(['id' => $id], 202)->header('Cache-Control', 'private, no-store');
    }

    public function cancel(Request $request, TrainingProgram $program, string $snapshot)
    {
        $kind = $request->validate(['kind' => ['required', Rule::in(['excel', 'documents'])]])['kind'];
        ExportStore::cancel($program, $snapshot, $kind);
        Log::info('Applicant export cancellation requested', ['snapshot_id' => $snapshot, 'user_id' => $request->user()->id]);

        return response()->noContent();
    }

    public function report(Request $request, TrainingProgram $program, string $snapshot, ExportFiles $files, ExportReport $report)
    {
        ExportStore::one($program->id, $snapshot);
        $lock = $files->lock($snapshot);
        abort_unless($lock, 409);
        try {
            $export = ExportStore::one($program->id, $snapshot);
            abort_unless(in_array($export->capture_state, ['ready', 'failed', 'cancelled'], true), 409, __('applicant_exports.report_pending'));
            $locale = $request->getPreferredLanguage(['en', 'ar']) ?? 'en';
            $workbook = $report->build($export, $locale);
            return response()->streamDownload(function () use ($lock, $workbook) {
                try {
                    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($workbook))->save('php://output');
                } finally {
                    $workbook->disconnectWorksheets();
                    fclose($lock);
                }
            }, "applicant-export-report-$snapshot.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable $exception) {
            fclose($lock);
            throw $exception;
        }
    }

    public function manifest(Request $request, TrainingProgram $program, string $snapshot, ExportFiles $files)
    {
        ExportStore::one($program->id, $snapshot);
        $lock = $files->lock($snapshot);
        abort_unless($lock, 404);
        $export = ExportStore::one($program->id, $snapshot);
        abort_unless($export->capture_state === 'ready', 409);
        $mappingComplete = $export->applicant_count === 0 || ExportStore::tasks()->where('export_id', $snapshot)
            ->where('kind', 'plan')->where('state', 'ready')->exists();

        Log::info('Applicant export manifest download', ['snapshot_id' => $snapshot, 'user_id' => $request->user()->id]);

        return response()->streamDownload(function () use ($export, $lock, $mappingComplete) {
            try {
                $header = json_encode([
                    'snapshot_id' => $export->id, 'captured_at' => $export->created_at,
                    'program_id' => $export->program_id, 'mapping_complete' => $mappingComplete,
                ], JSON_THROW_ON_ERROR);
                echo substr($header, 0, -1).',"applicants":[';
                $separator = '';
                foreach (ExportStore::rows($export->id)->lazyById(100, 'application_id') as $row) {
                    $documents = json_decode($row->documents, true, flags: JSON_THROW_ON_ERROR);
                    echo $separator.json_encode([
                        'application_id' => $row->application_id, 'part' => $mappingComplete ? $row->part : null,
                        'documents' => $documents,
                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                    $separator = ',';
                }
                echo ']}';
            } finally {
                fclose($lock);
            }
        }, "snapshot-$snapshot-manifest.json", ['Content-Type' => 'application/json', 'Cache-Control' => 'private, no-store']);
    }
}
