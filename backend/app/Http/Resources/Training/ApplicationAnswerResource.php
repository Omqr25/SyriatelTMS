<?php

namespace App\Http\Resources\Training;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationAnswerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'question_id' => $this->form_question_id,
            'prompt' => $this->question->prompt,
            'type' => $this->question->type->value,
            'text_value' => $this->text_value,
            'numeric_value' => $this->numeric_value,
            'options' => $this->options->map(fn ($option) => [
                'id' => $option->id,
                'label' => $option->label,
            ])->all(),
        ];
    }
}
