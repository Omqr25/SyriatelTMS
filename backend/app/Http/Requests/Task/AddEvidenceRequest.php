<?php

namespace App\Http\Requests\Task;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddEvidenceRequest extends FormRequest
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
        $fileRules = ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,gif'];

        return [
            // Retain compatibility with clients that still send a single file.
            'evidence' => ['required_without:evidences', 'prohibits:evidences', ...$fileRules],
            'evidences' => ['required_without:evidence', 'array', 'list', 'min:1', 'max:3'],
            'evidences.*' => ['required', ...$fileRules],
        ];
    }
}
