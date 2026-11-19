<?php

namespace App\Services\Training;

use App\Models\Application;
use App\Models\EnglishScoreImport;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnglishScoreImportService
{
    public const REVIEWABLE = ['ready', 'replacement', 'unchanged'];
    public const EXCEPTIONS = [
        'missing_email', 'invalid_email', 'unmatched', 'ambiguous', 'conflict',
        'missing_score', 'invalid_score', 'out_of_range', 'replacement_skipped', 'stale',
    ];

    public function __construct(private EnglishScoreWorkbook $workbook) {}

    public function preview(User $user, int $programId, UploadedFile $file): EnglishScoreImport
    {
        $input = collect($this->workbook->read($file->getRealPath()));
        $program = TrainingProgram::findOrFail($programId);
        $applicants = $this->applicants($programId);
        $matches = $this->byEmail($applicants);

        $duplicates = $input->where('email_valid', true)->groupBy('normalized_email');
        $rows = $input->map(function (array $row) use ($matches, $duplicates) {
            $email = $row['normalized_email'];
            $applicant = null;
            if ($email === '') {
                $status = 'missing_email';
            } elseif (! $row['email_valid']) {
                $status = 'invalid_email';
            } elseif (! $matches->has($email)) {
                $status = 'unmatched';
            } elseif ($matches[$email]->count() > 1) {
                $status = 'ambiguous';
            } else {
                $applicant = $matches[$email]->first();
                $group = $duplicates[$email];
                $status = $row['score_error'] ?? match (true) {
                    $group->pluck('score')->uniqueStrict()->count() > 1 => 'conflict',
                    $group->first()['row_number'] !== $row['row_number'] => 'duplicate',
                    $applicant->english_test_score === $row['score'] => 'unchanged',
                    $applicant->english_test_score !== null => 'replacement',
                    default => 'ready',
                };
            }

            return [
                'row_number' => $row['row_number'],
                'email' => $row['email'],
                'score_input' => $row['score_input'],
                'score' => $row['score'],
                'previous_score' => $applicant?->english_test_score,
                'application_id' => $applicant?->id,
                'status' => $status,
            ];
        });

        return DB::transaction(function () use ($user, $program, $file, $rows, $applicants) {
            TrainingProgram::sharedLock()->findOrFail($program->id);
            $batch = EnglishScoreImport::create([
                'training_program_id' => $program->id,
                'created_by' => $user->id,
                'program_name' => $program->name,
                'uploader_name' => $user->name,
                'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'summary' => $this->summary(
                    $rows->countBy('status')->all(),
                    $rows->whereNotNull('application_id')->count(),
                    $applicants,
                ),
                'expires_at' => now()->addMinutes(30),
            ]);

            foreach ($rows->chunk(100) as $chunk) {
                $batch->rows()->insert($chunk->map(fn (array $row) => [
                    'english_score_import_id' => $batch->id,
                ] + $row)->all());
            }

            return $batch;
        });
    }

    public function confirm(User $user, int $id, bool $replaceExisting): EnglishScoreImport
    {
        return DB::transaction(function () use ($user, $id, $replaceExisting) {
            $batch = EnglishScoreImport::where('created_by', $user->id)->lockForUpdate()->findOrFail($id);
            if ($batch->confirmed_at) {
                return $batch;
            }
            if ($batch->expires_at->isPast()) {
                throw ValidationException::withMessages(['file' => [__('english_scores.expired')]]);
            }

            // Serialize imports for this program, then lock current matches in a consistent order.
            // Rechecking the whole cohort also detects a newly ambiguous email.
            $program = TrainingProgram::lockForUpdate()->find($batch->training_program_id);
            $applicants = $program ? $this->applicants($program->id, lock: true) : collect();
            $matches = $this->byEmail($applicants);
            $reviewed = $batch->rows()->whereIn('status', self::REVIEWABLE)->orderBy('row_number')->get();

            foreach ($reviewed as $row) {
                $match = $matches->get(EnglishScoreWorkbook::normalizeEmail($row->email), collect());
                $applicant = $match->first();
                if ($match->count() !== 1 || $applicant->id !== $row->application_id) {
                    $row->status = 'stale';
                    $row->reason = 'match_changed';
                } elseif ($applicant->english_test_score !== $row->previous_score) {
                    $row->status = 'stale';
                    $row->reason = 'score_changed';
                } elseif ($row->status === 'replacement' && ! $replaceExisting) {
                    $row->status = 'replacement_skipped';
                } elseif ($row->status !== 'unchanged') {
                    $applicant->english_test_score = $row->score;
                    $applicant->save();
                    $row->status = 'applied';
                }
                if ($row->isDirty()) {
                    $row->save();
                }
            }

            $counts = $batch->rows()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
            $batch->update([
                'confirmed_at' => now(),
                'summary' => $this->summary($counts->all(), $batch->summary['matched_rows'], $applicants),
            ]);

            return $batch;
        }, 3);
    }

    private function applicants(int $programId, bool $lock = false): Collection
    {
        return Application::where('training_program_id', $programId)->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get(['id', 'email', 'english_test_score']);
    }

    private function byEmail(Collection $applicants): Collection
    {
        return $applicants->groupBy(fn (Application $applicant) => EnglishScoreWorkbook::normalizeEmail($applicant->email));
    }

    private function summary(array $counts, int $matchedRows, Collection $applicants): array
    {
        $counts = array_map('intval', $counts);
        $totalRows = array_sum($counts);
        $remaining = $applicants->whereNull('english_test_score')->count();

        return [
            'total_rows' => $totalRows,
            'matched_rows' => $matchedRows,
            'fully_matched' => $totalRows > 0 && $matchedRows === $totalRows,
            'applicant_count' => $applicants->count(),
            'applicants_without_scores' => $remaining,
            'exception_rows' => array_sum(array_intersect_key($counts, array_flip(self::EXCEPTIONS))),
            'counts' => $counts,
        ];
    }
}
