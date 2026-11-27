<?php

namespace App\Services\Certificate;

use App\Enums\CertificateEventAction;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateName;
use App\Models\CertificateNameWindow;
use App\Models\Trainee;
use App\Models\TrainingProgram;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CertificateNames
{
    public function __construct(private CertificateRoster $roster) {}

    public function window(TrainingProgram $program, ?DateTimeInterface $at = null): array
    {
        $now = $this->roster->time($at);
        $closes = $this->roster->cutoff($program);
        $automatic = $closes->subDay()->subDays(7);
        $manual = CertificateNameWindow::find($program->id)?->opened_at;
        $manualIsEarlier = $manual && $manual->lt($automatic);
        $opens = $manualIsEarlier ? $manual : $automatic;

        return [
            'state' => $now->gte($closes) ? 'closed' : ($now->lt($opens) ? 'scheduled' : 'open'),
            'opens_at' => $opens,
            'closes_at' => $closes,
            'source' => $manualIsEarlier ? 'manual' : 'automatic',
        ];
    }

    public function open(User $actor, TrainingProgram $program): CertificateNameWindow
    {
        Gate::forUser($actor)->authorize('manage', Certificate::class);

        return DB::transaction(function () use ($actor, $program) {
            $program = TrainingProgram::query()->lockForUpdate()->findOrFail($program->id);
            $now = $this->roster->time();
            if ($now->gte($this->roster->cutoff($program))) {
                throw ValidationException::withMessages(['name' => __('certificates.name_closed')]);
            }

            return CertificateNameWindow::firstOrCreate(['training_program_id' => $program->id], [
                'opened_by' => $actor->id, 'opened_at' => $now,
            ]);
        }, 3);
    }

    public function submit(User $actor, TrainingProgram $program, Trainee $trainee, string $value): CertificateName
    {
        return $this->save($actor, $program, $trainee, $value, CertificateName::TRAINEE);
    }

    public function saveManual(User $actor, TrainingProgram $program, Trainee $trainee, string $value): CertificateName
    {
        Gate::forUser($actor)->authorize('manage', Certificate::class);

        return $this->save($actor, $program, $trainee, $value, CertificateName::HR);
    }

    private function save(User $actor, TrainingProgram $program, Trainee $trainee, string $value, string $source): CertificateName
    {
        return DB::transaction(function () use ($actor, $program, $trainee, $value, $source) {
            $program = TrainingProgram::query()->lockForUpdate()->findOrFail($program->id);
            $trainee = $this->roster->lockTrainee($program, $trainee->id);
            if ($source === CertificateName::TRAINEE) {
                Gate::forUser($actor)->authorize('submitName', [Certificate::class, $trainee]);
                if ($this->window($program)['state'] !== 'open') {
                    throw ValidationException::withMessages(['name' => __('certificates.name_closed')]);
                }
            } else {
                $resolved = $this->resolve($program, $trainee);
                if ($resolved !== null && $resolved['source'] !== CertificateName::HR) {
                    throw ValidationException::withMessages(['name' => __('certificates.english_name_exists')]);
                }
            }
            if ($this->roster->existing($program, $trainee)) {
                throw ValidationException::withMessages(['name' => __('certificates.name_frozen')]);
            }

            $name = CertificateText::requireName($value);
            $identity = ['trainee_id' => $trainee->id, 'training_program_id' => $program->id, 'source' => $source];
            $entry = CertificateName::firstOrNew($identity);
            $previous = $entry->name;
            if ($previous === $name) {
                return $entry;
            }
            $entry->fill(['name' => $name, 'updated_by' => $actor->id])->save();

            if ($source === CertificateName::HR) {
                CertificateEvent::create([
                    'trainee_id' => $trainee->id, 'training_program_id' => $program->id, 'actor_id' => $actor->id,
                    'action' => CertificateEventAction::HrNameSaved, 'details' => ['previous' => $previous, 'name' => $name],
                ]);
            }

            return $entry;
        }, 3);
    }

    public function resolve(TrainingProgram $program, Trainee $trainee): ?array
    {
        if ((int) $trainee->application->training_program_id !== $program->id) {
            return null;
        }
        $entries = CertificateName::where('training_program_id', $program->id)->where('trainee_id', $trainee->id)
            ->pluck('name', 'source');

        // Trainee has no name column. Activation copies applications.full_name to users.name.
        $candidates = [
            CertificateName::TRAINEE => $entries->get(CertificateName::TRAINEE),
            'application' => $trainee->application->full_name,
            'account' => $trainee->user->name,
            CertificateName::HR => $entries->get(CertificateName::HR),
        ];
        foreach ($candidates as $source => $name) {
            if (($valid = CertificateText::name($name)) !== null) {
                return ['name' => $valid, 'source' => $source];
            }
        }

        return null;
    }
}
