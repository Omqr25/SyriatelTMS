<?php

namespace App\Models;

use App\Enums\TrainingProgramStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingProgram extends Model
{
    use HasFactory;

    protected $attributes = ['registration_has_opened' => false, 'revision' => 1];

    protected $fillable = [
        'name',
        'description',
        'application_start_date',
        'application_end_date',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'application_start_date' => 'date',
            'application_end_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => TrainingProgramStatus::class,
            'registration_has_opened' => 'boolean',
            'revision' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function phase(): string
    {
        $today = today();

        return match (true) {
            $this->archived_at !== null => 'archived',
            $today->lt($this->application_start_date) => 'upcoming',
            $today->lte($this->application_end_date) => 'registration_open',
            $today->lt($this->start_date) => 'registration_closed',
            $today->lte($this->end_date) => 'in_progress',
            default => 'completed',
        };
    }

    public function registrationStatus(): TrainingProgramStatus
    {
        return $this->phase() === 'registration_open' ? TrainingProgramStatus::OPEN : TrainingProgramStatus::CLOSED;
    }

    // Call on a model loaded with the three relationship existence flags.
    public function deletionBlock(): ?string
    {
        if ($this->archived_at) {
            return 'archived';
        }
        if ($this->registration_has_opened || today()->gte($this->application_start_date) || $this->status === TrainingProgramStatus::OPEN) {
            return 'opened';
        }
        if ($this->applications_exists || $this->programs_exists || $this->certificates_exists) {
            return 'linked';
        }

        return $this->registration_has_opened === null ? 'unknown_history' : null;
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }
}
