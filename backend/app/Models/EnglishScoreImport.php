<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnglishScoreImport extends Model
{
    use Prunable;

    protected $fillable = [
        'training_program_id', 'created_by', 'program_name', 'uploader_name',
        'file_name', 'summary', 'expires_at', 'confirmed_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(EnglishScoreImportRow::class);
    }

    public function prunable(): Builder
    {
        return $this->whereNull('confirmed_at')->where('expires_at', '<', now()->subDay());
    }
}
