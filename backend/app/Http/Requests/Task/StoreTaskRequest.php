<?php

namespace App\Http\Requests\Task;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        return self::taskRules();
    }

    public static function taskRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string','max:2000'],
            'deadline' => ['required', 'date', 'after:now'],
            'priority' => ['required', 'integer', 'between:1,3'],
            'instructions' => ['nullable', 'string'],
            'subtasks' => ['sometimes', 'array', 'max:50'],
            'subtasks.*' => ['array:title,completed'],
            'subtasks.*.title' => ['required', 'string', 'max:255'],
            'subtasks.*.completed' => ['required', 'boolean'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['required', 'url:http,https'],
            'trainee_ids' => ['sometimes', 'array', 'max:100'],
            'trainee_ids.*' => ['required', 'integer', 'distinct', 'exists:trainees,id'],
            'trainee_id' => ['nullable', 'integer', 'exists:trainees,id', 'prohibits:trainee_ids'],
        ];

    }
}
