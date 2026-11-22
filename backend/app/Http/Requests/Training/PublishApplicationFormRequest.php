<?php

namespace App\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;

class PublishApplicationFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'expected_revision' => ['required', 'integer', 'min:1'],
            'confirm_unopened' => ['sometimes', 'boolean'],
        ];
    }
}
