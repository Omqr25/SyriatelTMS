<?php

namespace App\Http\Requests\Training;

use App\Enums\FormQuestionType;
use App\Services\Training\ApplicationFormNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveApplicationFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'expected_revision' => ['required', 'integer', 'min:0'],
            'questions' => ['present', 'array', 'list'],
            'questions.*' => ['required', 'array:prompt,type,required,min_value,max_value,options'],
            'questions.*.prompt' => ['required', 'string', 'max:255'],
            'questions.*.type' => ['required', Rule::enum(FormQuestionType::class)],
            'questions.*.required' => ['required', 'boolean'],
            'questions.*.min_value' => ['nullable'],
            'questions.*.max_value' => ['nullable'],
            'questions.*.options' => ['sometimes', 'array', 'list'],
            'questions.*.options.*' => ['required', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach ($validator->getData()['questions'] as $index => $question) {
                    $key = "questions.$index";
                    $type = FormQuestionType::from($question['type']);
                    if (Str::trim($question['prompt']) === '') {
                        $validator->errors()->add("$key.prompt", __('application_form.meaningful'));
                    }
                    foreach ($question['options'] ?? [] as $optionIndex => $label) {
                        if (Str::trim($label) === '') {
                            $validator->errors()->add("$key.options.$optionIndex", __('application_form.meaningful'));
                        }
                    }

                    if (! $type->isChoice() && ! empty($question['options'])) {
                        $validator->errors()->add("$key.options", __('application_form.options_choice_only'));
                    }

                    $bounds = [];
                    foreach (['min_value', 'max_value'] as $bound) {
                        $value = $question[$bound] ?? null;
                        if ($value === null) {
                            continue;
                        }
                        if ($type !== FormQuestionType::Number) {
                            $validator->errors()->add("$key.$bound", __('application_form.bounds_number_only'));
                        } elseif (($bounds[$bound] = ApplicationFormNumber::parse($value)) === null) {
                            $validator->errors()->add("$key.$bound", __('application_form.number_format'));
                        }
                    }
                    if (isset($bounds['min_value'], $bounds['max_value']) && $bounds['min_value']->isGreaterThan($bounds['max_value'])) {
                        $validator->errors()->add("$key.max_value", __('application_form.bounds_order'));
                    }
                }
            },
        ];
    }
}
