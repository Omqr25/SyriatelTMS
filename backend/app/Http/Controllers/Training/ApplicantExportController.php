<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\TrainingProgram;
use App\Services\Training\ApplicantExport\ExportFiles;
use App\Services\Training\ApplicantExport\ExportQueue;
use App\Services\Training\ApplicantExport\ExportStore;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ApplicantExportController extends Controller
{
    public function index(TrainingProgram $program)
    {
        $snapshots = ExportStore::exports()->where('program_id', $program->id)->where('expires_at', '>', now())
            ->orderByRaw('operation IS NULL')->orderByDesc('id')->limit(20)->get();
        $tasks = ExportStore::tasks()->whereIn('export_id', $snapshots->pluck('id'))->where('kind', '!=', 'prune')
            ->orderBy('part')->get(['id', 'export_id', 'kind', 'part', 'state', 'estimate', 'progress', 'bytes', 'sha256', 'error'])
            ->groupBy('export_id');
        $result = $snapshots->map(function ($snapshot) use ($tasks) {
            foreach (['created_at', 'updated_at', 'expires_at'] as $attribute) {
                $snapshot->{$attribute} = Carbon::parse($snapshot->{$attribute})->toIso8601String();
            }
            unset($snapshot->columns, $snapshot->next_operation, $snapshot->created_by);
            $snapshot->tasks = ($tasks[$snapshot->id] ?? collect())->map(function ($task) {
                $task->oversized = $task->kind === 'zip' && $task->estimate > config('applicant_exports.part_bytes');
                unset($task->estimate, $task->export_id);
                return $task;
            });

            return $snapshot;
        });

        return response()->json(['data' => $result])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, TrainingProgram $program, ExportQueue $queue)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['capture', 'excel', 'documents'])],
            'fresh' => ['sometimes', 'boolean'], 'snapshot_id' => ['sometimes', 'nullable', 'ulid'],
        ]);
        $id = ExportStore::request($program, $request->user()->id, $data['kind'], $request->boolean('fresh'), $data['snapshot_id'] ?? null);
        Log::info('Applicant export requested', ['snapshot_id' => $id, 'program_id' => $program->id, 'user_id' => $request->user()->id, 'kind' => $data['kind']]);
        $queue->dispatch($id);

        return response()->json(['id' => $id], 202)->header('Cache-Control', 'private, no-store');
    }

    public function retry(Request $request, TrainingProgram $program, string $snapshot, ExportQueue $queue)
    {
        $id = ExportStore::retry($program, $snapshot);
        Log::info('Applicant export retry requested', ['snapshot_id' => $id, 'user_id' => $request->user()->id]);
        $queue->dispatch($id);

        return response()->json(['id' => $id], 202)->header('Cache-Control', 'private, no-store');
    }

    public function cancel(Request $request, TrainingProgram $program, string $snapshot)
    {
        ExportStore::cancel($program, $snapshot);
        Log::info('Applicant export cancellation requested', ['snapshot_id' => $snapshot, 'user_id' => $request->user()->id]);

        return response()->noContent();
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
