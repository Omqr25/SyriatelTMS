<?php

namespace App\Models;

use App\Enums\FormQuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormQuestion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => FormQuestionType::class,
            'required' => 'boolean',
            'min_value' => 'decimal:4',
            'max_value' => 'decimal:4',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('display_order')->orderBy('id');
    }
}
