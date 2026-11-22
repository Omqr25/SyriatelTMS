<?php

namespace App\Http\Requests\Training;

use App\Enums\City;
use App\Enums\FieldOfStudy;
use App\Enums\HowHeard;
use App\Enums\YearOfStudy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use JsonException;
use stdClass;

class StoreApplicationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $answers = $this->input('answers');
        if (! is_string($answers)) {
            return;
        }

        try {
            $decoded = json_decode($answers, false, 8, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            return;
        }

        if (is_array($decoded)) {
            $this->merge(['answers' => array_map(
                fn ($entry) => $entry instanceof stdClass ? (array) $entry : $entry,
                $decoded,
            )]);
        }
    }

    public function messages(): array
    {
        $messages = [];

        foreach (['cv', 'personal_photo', 'national_id', 'academic_qualification'] as $field) {
            $file = $this->file($field);
            if (! $file instanceof UploadedFile || $file->isValid()) {
                continue;
            }

            $message = match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'size',
                UPLOAD_ERR_PARTIAL => 'partial',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'server',
                default => 'failed',
            };
            $messages["{$field}.uploaded"] = __("application_upload.{$message}");
        }

        return $messages;
    }

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
        $identifier = static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_int($value) && ! is_string($value)) {
                $fail('validation.integer')->translate();
            }
        };

        return [
            'training_program_id' => ['required', 'integer', 'exists:training_programs,id'],
            'application_form_id' => ['bail', 'sometimes', 'nullable', $identifier, 'integer', 'min:1'],
            'answers' => ['sometimes', 'array', 'list'],
            'answers.*' => ['required', 'array:question_id,text_value,numeric_value,option_ids'],
            'answers.*.question_id' => ['bail', 'required', $identifier, 'integer', 'min:1', 'distinct'],
            'answers.*.text_value' => ['sometimes', 'string'],
            'answers.*.numeric_value' => ['sometimes'],
            'answers.*.option_ids' => ['sometimes', 'array', 'list'],
            'answers.*.option_ids.*' => ['bail', 'required', $identifier, 'integer', 'min:1'],
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
            'home_address' => ['required', 'string','max:200'],
            'university'   =>['required', 'string','max:30'],
            'field_of_study' => ['required', 'string',Rule::enum(FieldOfStudy::class)],
            'academic_major' => ['required', 'string', 'max:30'],
            'technical_skills' => ['required', 'string','max:100'],
//            'national_id_number' => ['required', 'integer', 'digits:11', 'unique:applications,national_id_number'],
            'national_id_number' => ['required', 'numeric', 'regex:/^[0-9]{11}$/', 'unique:applications,national_id_number'],
        ];
    }
}
