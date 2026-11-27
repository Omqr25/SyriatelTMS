<?php

namespace App\Services\Certificate;

use App\Enums\CertificateEventAction;
use App\Enums\CertificateState;
use App\Models\Certificate;
use App\Models\CertificateBatch;
use App\Models\CertificateEvent;
use App\Models\CertificateTemplate;
use App\Models\CertificateTemplateVersion;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CertificateReservations
{
    public function __construct(
        private CertificateRoster $roster,
        private CertificateNames $names,
        private CertificatePlaceholders $placeholders,
    ) {}

    /** Records confirmed durable reservations. Queue dispatch is a subsequent integration phase. */
    public function confirm(
        User $actor, TrainingProgram $program, CertificateTemplateVersion $version, array $traineeIds,
        string $requestKey, bool $confirmed, ?string $overrideReason = null,
    ): CertificateBatch {
        Gate::forUser($actor)->authorize('manage', Certificate::class);
        Validator::validate(['trainees' => $traineeIds, 'request' => $requestKey, 'confirmed' => $confirmed], [
            'trainees' => 'required|array|list', 'trainees.*' => 'required|integer|min:1',
            'request' => 'required|uuid', 'confirmed' => 'accepted',
        ]);
        $ids = array_values(array_unique(array_map('intval', $traineeIds)));
        sort($ids, SORT_NUMERIC);
        $requestKey = strtolower($requestKey);
        if ($overrideReason !== null) {
            if (count($ids) !== 1) {
                $this->reject('override_individual');
            }
            $overrideReason = CertificateText::reason($overrideReason);
        }

        $requestHash = hash('sha256', json_encode([
            $actor->id, $program->id, $version->id, $ids, $overrideReason,
        ], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($actor, $program, $version, $ids, $requestKey, $requestHash, $overrideReason) {
                $program = TrainingProgram::lockForUpdate()->findOrFail($program->id);
                if ($previous = CertificateBatch::where('request_key', $requestKey)->first()) {
                    return $this->matchRequest($previous, $requestHash);
                }

                $version = CertificateTemplateVersion::findOrFail($version->id);
                $template = CertificateTemplate::lockForUpdate()->findOrFail($version->certificate_template_id);
                if ($template->archived_at !== null) {
                    $this->reject('template_archived');
                }

                $trainees = array_map(fn (int $id) => $this->roster->lockTrainee($program, $id), $ids);
                $confirmedAt = $this->roster->time();
                $issueDate = $confirmedAt->toDateString();
                if ($confirmedAt->lt($this->roster->cutoff($program))) {
                    $this->reject('program_not_ended');
                }
                $batch = CertificateBatch::create([
                    'training_program_id' => $program->id, 'certificate_template_version_id' => $version->id,
                    'confirmed_by' => $actor->id, 'confirmed_at' => $confirmedAt, 'issue_date' => $issueDate,
                    'request_key' => $requestKey, 'request_hash' => $requestHash,
                ]);

                $used = $this->placeholders->used($version->configuration);
                foreach ($trainees as $trainee) {
                    $status = $this->roster->status($program, $trainee, $confirmedAt);
                    if ($overrideReason === null ? $status !== 'ready' : ! in_array($status, ['excluded', 'fired'], true)) {
                        $this->reject('not_eligible', $trainee->id);
                    }
                    $resolved = $this->names->resolve($program, $trainee);
                    if ($resolved === null) {
                        $this->reject('english_name_required', $trainee->id);
                    }
                    $number = $this->nextNumber($confirmedAt->year);
                    $certificate = new Certificate;
                    $certificate->forceFill([
                        'training_program_id' => $program->id, 'trainee_id' => $trainee->id,
                        'certificate_batch_id' => $batch->id, 'certificate_template_version_id' => $version->id,
                        'issue_date' => $issueDate, 'number' => $number, 'state' => CertificateState::Queued,
                        'snapshot' => [
                            'trainee_name' => $resolved['name'], 'name_source' => $resolved['source'],
                            'values' => (object) $this->placeholders->resolve($used, $program, $trainee, $resolved['name'], $issueDate, $number),
                        ],
                    ])->save();

                    if ($overrideReason !== null) {
                        CertificateEvent::create([
                            'training_program_id' => $program->id, 'trainee_id' => $trainee->id, 'certificate_id' => $certificate->id,
                            'actor_id' => $actor->id, 'action' => CertificateEventAction::Overridden, 'reason' => $overrideReason,
                            'details' => ['trainee_status' => $trainee->status->value, 'eligibility' => $status, 'number' => $number],
                        ]);
                    }
                }

                return $batch;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if ($previous = CertificateBatch::where('request_key', $requestKey)->first()) {
                return $this->matchRequest($previous, $requestHash);
            }

            throw $exception;
        }
    }

    private function nextNumber(int $year): string
    {
        $sequence = DB::table('certificate_number_sequences');
        $sequence->insertOrIgnore(['year' => $year, 'last_number' => 0]);
        $sequence->where('year', $year)->increment('last_number');
        $number = $sequence->where('year', $year)->value('last_number');

        return sprintf('CERT-%04d-%06d', $year, $number);
    }

    private function matchRequest(CertificateBatch $previous, string $requestHash): CertificateBatch
    {
        if ($previous->request_hash !== $requestHash) {
            $this->reject('reservation_conflict');
        }

        return $previous;
    }

    private function reject(string $key, ?int $trainee = null): never
    {
        throw ValidationException::withMessages(['issuance' => __('certificates.'.$key, ['trainee' => $trainee])]);
    }
}
