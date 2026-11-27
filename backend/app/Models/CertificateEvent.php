<?php

namespace App\Models;

use App\Enums\CertificateEventAction;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class CertificateEvent extends Model
{
    protected $fillable = [
        'trainee_id', 'training_program_id', 'certificate_id', 'actor_id', 'action', 'reason', 'details',
    ];

    protected function casts(): array
    {
        return ['action' => CertificateEventAction::class, 'details' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Certificate audit events are immutable.'));
        static::deleting(fn () => throw new LogicException('Certificate audit events must be retained.'));
    }
}
