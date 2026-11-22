<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationForm extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['revision' => 1];

    protected function casts(): array
    {
        return ['revision' => 'integer', 'published_at' => 'datetime'];
    }

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(FormQuestion::class)->orderBy('display_order')->orderBy('id');
    }
}
