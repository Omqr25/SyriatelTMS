<?php

namespace App\Http\Requests\Organization;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTrainerRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:30'],
            'email' => ['sometimes', 'email', 'max:30', 'unique:users,email',],
            'phone_number' => ['sometimes', 'string', 'max:10', 'regex:/^09[0-9]{8}$/',],
            'department_id' => ['sometimes', 'integer', 'exists:departments,id',],
        ];
    }
}
