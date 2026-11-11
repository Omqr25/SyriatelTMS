<?php

namespace App\Http\Requests\Task;

use App\Models\Milestone;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTaskRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'deadline' => ['sometimes', 'date', 'after:today'],
            'priority' => ['sometimes', 'integer', 'between:1,10'],
            'instructions' => ['sometimes', 'string'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['string', 'required'],
            'trainee_id' => ['sometimes','integer', 'exists:trainees,id'],
        ];
    }
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $milestone_id = $this->route('milestone_id');
                $milestone = Milestone::with('program')->find($milestone_id);
                if (! $milestone || ! $this->filled('deadline')) {
                    return;
                }
                $program = $milestone->program;
                if (! $program?->deadline) {
                    return;
                }
                if (! Carbon::parse($this->input('deadline'))->lt($program->deadline)) {
                    $validator->errors()->add(
                        'deadline',
                        "The task deadline must be before the program deadline {$program->deadline->format('Y-m-d H:i')}."
                    );
                }
            },
        ];
    }
}
