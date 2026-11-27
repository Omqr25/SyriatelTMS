<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CertificatePackage extends Model
{
    public const ACTIVE = ['queued', 'processing'];

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['certificate_ids' => 'array', 'total' => 'integer', 'processed' => 'integer',
            'dispatch' => 'integer', 'started_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
