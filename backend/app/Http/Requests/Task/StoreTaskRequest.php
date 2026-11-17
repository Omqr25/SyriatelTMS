<?php

namespace App\Http\Requests\Task;

use App\Models\Milestone;
use App\Models\Program;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTaskRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string','max:2000'],
            'deadline' => ['required', 'date', 'after:now'],
            'priority' => ['nullable', 'integer', 'between:1,10'],
            'instructions' => ['nullable', 'string'],
            'subtasks' => ['sometimes', 'array', 'max:50'],
            'subtasks.*' => ['array:title,completed'],
            'subtasks.*.title' => ['required', 'string', 'max:255'],
            'subtasks.*.completed' => ['required', 'boolean'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['string', 'required'],
            'trainee_id' => ['required','integer', 'exists:trainees,id'],
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
//                $program = $milestone->program;
//                if (! $program?->deadline) {
//                    return;
//                }
                $programDeadline = $milestone->program?->deadline;

                if (!$programDeadline) {
                    return;
                }
                $taskDeadline = Carbon::parse($this->input('deadline'));
                $programDeadline = Carbon::parse($programDeadline);
                if ( $taskDeadline > $programDeadline) {
                    $validator->errors()->add(
                        'deadline',
                        "The task deadline must be before the program deadline {$programDeadline->format('Y-m-d H:i')}."
                    );
                }
            },
        ];
    }
}
