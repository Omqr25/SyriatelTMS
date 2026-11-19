<?php

namespace App\Http\Requests\Training;

class UpdateTrainingProgramRequest extends StoreTrainingProgramRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['draft']);
        // Retain support for partial updates and existing longer names/descriptions.
        $rules['name'] = ['required', 'string', 'max:255'];
        $rules['description'] = ['nullable', 'string'];
        foreach ($rules as $field => $fieldRules) {
            $rules[$field] = ['sometimes', ...$fieldRules];
        }

        return $rules + [
            'confirm_date_change' => ['sometimes', 'boolean'],
            'expected_revision' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
