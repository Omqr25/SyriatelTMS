<?php

namespace App\Http\Requests\Task;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'deadline' => ['sometimes', 'date', 'after:now'],
            'priority' => ['sometimes', 'integer', 'between:1,3'],
            'instructions' => ['sometimes', 'nullable', 'string'],
            'subtasks' => ['sometimes', 'array', 'max:50'],
            'subtasks.*' => ['array:title,completed'],
            'subtasks.*.title' => ['required', 'string', 'max:255'],
            'subtasks.*.completed' => ['required', 'boolean'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['required', 'url:http,https'],
            'trainee_id' => ['sometimes', 'nullable', 'integer', 'exists:trainees,id'],
        ];
    }
}
