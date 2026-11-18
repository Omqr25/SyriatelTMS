<?php

namespace App\Http\Requests\Task;

use App\Enums\TrainingProgramStatus;
use App\Models\TrainingProgram;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProgramRequest extends FormRequest
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
        $rules = [
            'title' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string','max:100'],
            'deadline' => ['required', 'date', 'after:today'],
            'phases' => ['required', 'array', 'min:1'],
            'phases.*' => ['array:name,description,tasks'],
            'phases.*.tasks' => ['sometimes', 'array', 'max:50'],
            'phases.*.tasks.*' => ['array:'.implode(',', array_filter(array_keys(StoreTaskRequest::taskRules()), fn ($key) => !str_contains($key, '.')))],
            'phases.*.name' => ['required', 'string', 'max:30'],
            'phases.*.description' => ['nullable', 'string','max:100'],

        ];
        foreach (StoreTaskRequest::taskRules() as $field => $constraints) {
            $rules['phases.*.tasks.*.'.$field] = array_map(
                fn ($rule) => $rule === 'prohibits:trainee_ids' ? 'prohibits:phases.*.tasks.*.trainee_ids' : $rule,
                $constraints,
            );
        }
        return $rules;
    }
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $training_program=TrainingProgram::where('status', TrainingProgramStatus::OPEN)->latest()->first();
                if (! $training_program || ! $this->filled('deadline') || $validator->errors()->has('deadline')) {
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
