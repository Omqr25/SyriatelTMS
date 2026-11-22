<?php

namespace App\Services\Training;

use App\Enums\FormQuestionType;
use App\Exceptions\ErrorException;
use App\Models\Application;
use App\Models\ApplicationForm;
use App\Models\FormQuestion;
use App\Models\TrainingProgram;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApplicationAnswerService
{
    public function resolveForm(TrainingProgram $program, ?int $formId, array $answers): ?ApplicationForm
    {
        $form = $program->publishedApplicationForm()->first();
        if ($formId !== $form?->id || (! $form && $answers !== [])) {
            throw new ErrorException(__('application_form.changed'), 409, ['reason' => 'application_form_changed']);
        }

        return $form;
    }

    public function validate(ApplicationForm $form, array $answers): array
    {
        $questions = $form->questions()->with('options')->get()->keyBy('id');
        $values = [];
        $seen = [];
        $errors = [];

        foreach ($answers as $index => $input) {
            $id = (int) $input['question_id'];
            $key = "answers.$index";
            if (isset($seen[$id])) {
                $errors["$key.question_id"] = __('application_form.duplicate_question');

                continue;
            }
            $seen[$id] = true;
            $question = $questions->get($id);
            if (! $question) {
                $errors["$key.question_id"] = __('application_form.unknown_question');

                continue;
            }

            $valueKey = match ($question->type) {
                FormQuestionType::Text => 'text_value',
                FormQuestionType::Number => 'numeric_value',
                default => 'option_ids',
            };
            if (! array_key_exists($valueKey, $input) || array_diff(array_keys($input), ['question_id', $valueKey])) {
                $errors["$key.$valueKey"] = __('application_form.wrong_type');

                continue;
            }

            $value = $this->validateValue($question, $valueKey, $input[$valueKey]);
            if ($value === null) {
                continue;
            }
            if (isset($value['error'])) {
                $errors["$key.$valueKey"] = $value['error'];

                continue;
            }
            $values[$id] = $value;
        }

        foreach ($questions as $id => $question) {
            if ($question->required && ! isset($values[$id])) {
                $errors["questions.$id"] = __('application_form.required');
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $values;
    }

    private function validateValue(FormQuestion $question, string $key, mixed $value): ?array
    {
        if ($key === 'text_value') {
            if (! is_string($value)) {
                return ['error' => __('application_form.wrong_type')];
            }

            $value = Str::trim($value);

            return match (true) {
                $value === '' => null,
                Str::length($value) > 100 => ['error' => __('application_form.text_length')],
                default => [$key => $value],
            };
        }

        if ($key === 'numeric_value') {
            $number = ApplicationFormNumber::parse($value);
            if ($number === null) {
                return ['error' => __('application_form.number_format')];
            }
            if ($question->min_value !== null && $number->isLessThan($question->min_value)) {
                return ['error' => __('application_form.number_min', ['min' => $question->min_value])];
            }
            if ($question->max_value !== null && $number->isGreaterThan($question->max_value)) {
                return ['error' => __('application_form.number_max', ['max' => $question->max_value])];
            }

            return [$key => (string) $number];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            return ['error' => __('application_form.wrong_type')];
        }

        $ids = array_map('intval', $value);
        if ($ids === [] || ($question->type === FormQuestionType::SingleChoice && count($ids) !== 1)) {
            return ['error' => __('application_form.choice_count')];
        }
        if (count($ids) !== count(array_unique($ids)) || array_diff($ids, $question->options->modelKeys())) {
            return ['error' => __('application_form.invalid_options')];
        }

        return [$key => $ids];
    }

    public function save(Application $application, array $values): void
    {
        foreach ($values as $questionId => $value) {
            $answer = $application->answers()->create([
                'application_form_id' => $application->application_form_id,
                'form_question_id' => $questionId,
                'text_value' => $value['text_value'] ?? null,
                'numeric_value' => $value['numeric_value'] ?? null,
            ]);
            if (isset($value['option_ids'])) {
                $answer->options()->attach($value['option_ids'], ['form_question_id' => $questionId]);
            }
        }
    }
}
