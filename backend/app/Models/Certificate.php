<?php

namespace App\Models;

use App\Enums\CertificateState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Certificate extends Model
{
    use HasFactory;

    // The existing manual-upload flow still produces issued legacy records.
    protected $attributes = ['state' => 'issued', 'generation_attempts' => 0];

    protected $fillable = ['path', 'trainee_id', 'training_program_id', 'issue_date'];

    protected function casts(): array
    {
        return [
            'state' => CertificateState::class,
            'issue_date' => 'immutable_date',
            'snapshot' => 'array',
            'generation_attempts' => 'integer',
            'generation_started_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $certificate) {
            if ($certificate->isDirty([
                'trainee_id', 'training_program_id', 'number', 'issue_date', 'certificate_batch_id',
                'certificate_template_version_id', 'snapshot',
            ]) || $certificate->getOriginal('state') === CertificateState::Issued) {
                throw new LogicException('Certificate identity, snapshots and issued records are immutable.');
            }

            if ($certificate->isDirty('state') && ! $certificate->getOriginal('state')->canTransitionTo($certificate->state)) {
                throw new LogicException('Invalid certificate state transition.');
            }
        });

        static::deleting(fn () => throw new LogicException('Certificates must be retained.'));
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('state', CertificateState::Issued);
    }

    public function trainee(): BelongsTo
    {
        return $this->belongsTo(Trainee::class);
    }

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CertificateBatch::class, 'certificate_batch_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplateVersion::class, 'certificate_template_version_id');
    }
}
