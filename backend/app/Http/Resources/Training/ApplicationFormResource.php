<?php

namespace App\Http\Resources\Training;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationFormResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'questions' => $this->questions->map(fn ($question) => [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'type' => $question->type->value,
                'required' => $question->required,
                'min_value' => $question->min_value,
                'max_value' => $question->max_value,
                'options' => $question->options->map(fn ($option) => [
                    'id' => $option->id,
                    'label' => $option->label,
                ])->all(),
            ])->all(),
        ];
    }
}
