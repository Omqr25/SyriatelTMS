<?php

namespace App\Http\Requests\Training;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInterviewRequest extends FormRequest
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
            'trainer_id' => ['required', 'integer', 'exists:trainers,id'],
            'time' => ['required', 'date', 'after:now'],
            'location' => ['required', 'string', 'max:255'],
        ];
    }
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('time') || ! $this->filled('time')) {
                    return;
                }

                $time = Carbon::parse($this->input('time'));

                $start = $time->copy()->setTime(8, 30);
                $end = $time->copy()->setTime(15, 0);
                if ($time->lt($start) || $time->gt($end)) {
                    $validator->errors()->add(
                        'time',
                        'The interview time must be between 08:30 and 15:00.'
                    );
                }
            },
        ];
    }
}
