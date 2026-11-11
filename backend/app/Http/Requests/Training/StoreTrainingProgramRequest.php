<?php

namespace App\Http\Requests\Training;

use App\Rules\NoProgramDateOverlap;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingProgramRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:30'],
            'description' => ['nullable', 'string','max:100'],
            'application_start_date' => ['required', 'date', new NoProgramDateOverlap(
                'application_start_date',
                'application_end_date'
            )],
            'application_end_date' => ['required', 'date', 'after_or_equal:application_start_date'],
            'start_date' => ['required', 'date', 'after:application_end_date',new NoProgramDateOverlap(
                'start_date',
                'end_date'
            )],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],

        ];
    }
}
