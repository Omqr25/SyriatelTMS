<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVacationRequest extends FormRequest
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
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date'   => ['required', 'date_format:Y-m-d'],
            'reason'     => ['required', 'string', 'max:255'],
        ];
    }
}
