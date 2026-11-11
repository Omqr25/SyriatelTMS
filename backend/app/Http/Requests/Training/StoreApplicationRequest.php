<?php

namespace App\Http\Requests\Training;

use App\Enums\City;
use App\Enums\FieldOfStudy;
use App\Enums\HowHeard;
use App\Enums\YearOfStudy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends FormRequest
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
            'training_program_id' => ['required', 'integer', 'exists:training_programs,id'],
            'full_name' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:30','unique:applications,email','unique:users,email'],
            'phone_number' => ['required', 'string', 'max:10', 'regex:/^09[0-9]{8}$/',],
            'birth_date' => ['required', 'date', 'before:' . today()->subYears(20)->toDateString(),],
            'city' => ['required', Rule::enum(City::class)],
            'year_of_study' => ['required', Rule::enum(YearOfStudy::class)],
            'gpa' => ['required', 'numeric', 'between:0,100'],
            'commitment' => ['required', 'boolean'],
            'previous_training' => ['required', 'boolean'],
            'how_heard' => ['required', Rule::enum(HowHeard::class)],
            'additional_info' => ['nullable', 'string','max:100'],
            'cv' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'national_id' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'academic_qualification' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'personal_photo' => ['required', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'home_address' => ['required', 'string','max:100'],
            'university'   =>['required', 'string','max:30'],
            'field_of_study' => ['required', 'string',Rule::enum(FieldOfStudy::class)],
            'academic_major' => ['required', 'string', 'max:30'],
            'technical_skills' => ['required', 'string','max:100'],
//            'national_id_number' => ['required', 'integer', 'digits:11', 'unique:applications,national_id_number'],
            'national_id_number' => ['required', 'numeric', 'regex:/^[0-9]{11}$/', 'unique:applications,national_id_number'],
        ];
    }
}
