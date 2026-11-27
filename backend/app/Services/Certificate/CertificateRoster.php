<?php

namespace App\Services\Certificate;

use App\Enums\CertificateEventAction;
use App\Enums\TraineeStatus;
use App\Models\Application;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\Trainee;
use App\Models\TrainingProgram;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CertificateRoster
{
    public function time(?DateTimeInterface $at = null): CarbonImmutable
    {
        return $at ? CarbonImmutable::instance($at)->setTimezone(config('app.timezone')) : CarbonImmutable::now(config('app.timezone'));
    }

    public function cutoff(TrainingProgram $program): CarbonImmutable
    {
        return CarbonImmutable::parse($program->end_date->toDateString(), config('app.timezone'))->addDay();
    }

    /** Call after locking the training program inside a transaction. */
    public function lockTrainee(TrainingProgram $program, int $traineeId): Trainee
    {
        $trainee = Trainee::query()->lockForUpdate()->findOrFail($traineeId);
        $application = Application::query()->lockForUpdate()->findOrFail($trainee->application_id);
        if ((int) $application->training_program_id !== $program->id) {
            throw ValidationException::withMessages(['trainee' => __('certificates.wrong_program')]);
        }
        $trainee->setRelation('application', $application);

        return $trainee;
    }

    public function exclusion(TrainingProgram $program, Trainee $trainee): bool
    {
        return CertificateEvent::where('training_program_id', $program->id)
            ->where('trainee_id', $trainee->id)
            ->whereIn('action', [CertificateEventAction::Excluded, CertificateEventAction::Restored])
            ->latest('id')->value('action') === CertificateEventAction::Excluded;
    }

    public function existing(TrainingProgram $program, Trainee $trainee): ?Certificate
    {
        return Certificate::whereBelongsTo($program, 'trainingProgram')->whereBelongsTo($trainee)->first();
    }

    public function status(TrainingProgram $program, Trainee $trainee, ?DateTimeInterface $at = null): string
    {
        if ((int) $trainee->application->training_program_id !== $program->id) {
            return 'wrong_program';
        }
        if ($certificate = $this->existing($program, $trainee)) {
            return $certificate->state->value;
        }
        if ($this->time($at)->lt($this->cutoff($program))) {
            return 'program_not_ended';
        }
        if ($this->exclusion($program, $trainee)) {
            return 'excluded';
        }

        return $trainee->status === TraineeStatus::FIRED ? 'fired' : 'ready';
    }

    public function setExcluded(User $actor, TrainingProgram $program, Trainee $trainee, bool $exclude, string $reason): CertificateEvent
    {
        Gate::forUser($actor)->authorize('manage', Certificate::class);
        $reason = CertificateText::reason($reason);

        return DB::transaction(function () use ($actor, $program, $trainee, $exclude, $reason) {
            $program = TrainingProgram::query()->lockForUpdate()->findOrFail($program->id);
            $trainee = $this->lockTrainee($program, $trainee->id);
            if ($exclude ? $this->status($program, $trainee) !== 'ready' :
                ! $this->exclusion($program, $trainee) || $this->existing($program, $trainee)) {
                throw ValidationException::withMessages(['trainee' => __('certificates.invalid_roster_change')]);
            }

            return CertificateEvent::create([
                'training_program_id' => $program->id, 'trainee_id' => $trainee->id,
                'actor_id' => $actor->id, 'action' => $exclude ? CertificateEventAction::Excluded : CertificateEventAction::Restored,
                'reason' => $reason,
            ]);
        }, 3);
    }
}
