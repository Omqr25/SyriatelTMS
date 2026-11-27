<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CertificateBatch extends Model
{
    protected $fillable = [
        'request_key', 'request_hash', 'training_program_id', 'certificate_template_version_id',
        'confirmed_by', 'confirmed_at', 'issue_date',
    ];

    protected function casts(): array
    {
        return ['confirmed_at' => 'immutable_datetime', 'issue_date' => 'immutable_date'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Certificate reservations are immutable.'));
        static::deleting(fn () => throw new LogicException('Certificate reservations must be retained.'));
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplateVersion::class, 'certificate_template_version_id');
    }
}
