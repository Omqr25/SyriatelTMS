<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ApplicationAnswer extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['numeric_value' => 'decimal:4'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(FormQuestion::class, 'form_question_id');
    }

    public function options(): BelongsToMany
    {
        return $this->belongsToMany(QuestionOption::class, 'answer_options')
            ->withTimestamps()
            ->orderBy('question_options.display_order')
            ->orderBy('question_options.id');
    }
}
