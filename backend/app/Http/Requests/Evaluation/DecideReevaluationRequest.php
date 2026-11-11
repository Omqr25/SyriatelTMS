<?php

namespace App\Http\Requests\Evaluation;

use App\Enums\EvaluationRequestStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideReevaluationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(EvaluationRequestStatus::cases()),],
            'decision_note' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'indicators' => [Rule::requiredIf(fn () => $this->input('decision') === EvaluationRequestStatus::ACCEPTED->value),
                'array', 'min:1'],
            'indicators.*.name' => [Rule::requiredIf(fn () => $this->input('decision') === EvaluationRequestStatus::ACCEPTED->value),
                'string', 'max:30'],
            'indicators.*.score' => [Rule::requiredIf(fn () => $this->input('decision') === EvaluationRequestStatus::ACCEPTED->value),
                'numeric', 'between:0,100'],
            'indicators.*.notice' => ['nullable', 'string', 'max:100'],
        ];
    }
}
