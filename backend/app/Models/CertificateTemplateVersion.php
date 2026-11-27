<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CertificateTemplateVersion extends Model
{
    protected $fillable = ['version', 'background_path', 'background_sha256', 'configuration', 'created_by'];

    protected function casts(): array
    {
        return ['configuration' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Certificate template versions are immutable.'));
        static::deleting(fn () => throw new LogicException('Certificate template versions must be retained.'));
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }
}
