<?php

namespace App\Http\Requests\Task;

use App\Enums\TrainingProgramStatus;
use App\Models\TrainingProgram;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProgramRequest extends FormRequest
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
        'title' => ['sometimes', 'required', 'string', 'max:255'],
        'description' => ['sometimes', 'nullable', 'string'],
        'deadline' => ['sometimes', 'required', 'date', 'after:today'],
            'phases' => ['sometimes', 'array'],
            'phases.*.id' => ['sometimes', 'integer', 'distinct'],
            'phases.*.name' => ['required', 'string', 'max:255'],
            'phases.*.description' => ['required', 'nullable', 'string'],
        ];

    }
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $training_program=TrainingProgram::where('status', TrainingProgramStatus::OPEN)->latest()->first();
                if (! $training_program || ! $this->filled('deadline')) {
                    return;
                }

                if ($training_program->end_date && ! \Carbon\Carbon::parse($this->input('deadline'))->lt($training_program->end_date)
                ) {
                    $validator->errors()->add(
                        'deadline',
                        "The program deadline must be before the training program end date {$training_program->end_date->format('Y-m-d')}.}"
                    );
                }
            },
        ];
    }
}
