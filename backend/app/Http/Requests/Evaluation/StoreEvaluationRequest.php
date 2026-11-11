<?php

namespace App\Http\Requests\Evaluation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluationRequest extends FormRequest
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
//            'score' => 'required|integer|min:0|max:100',
            'description' => ['nullable', 'string', 'max:100'],
            'indicators' => ['required', 'array', 'min:1'],
            'indicators.*.name' => ['required', 'string', 'max:30'],
            'indicators.*.score' => ['required', 'numeric', 'between:0,100'],
            'indicators.*.notice' => ['nullable', 'string', 'max:100'],
        ];
    }
}
