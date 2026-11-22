<?php

namespace App\Http\Requests\Announcemnts;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class StoreAnnoncementRequest extends FormRequest
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
            'department_id' => $this->user()?->roles()->where('role', UserRole::TRAINING_TEAM)->exists()
                ? ['sometimes', 'nullable', 'integer', 'exists:departments,id'] : ['prohibited'],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:2000'],
            ];
    }
}
