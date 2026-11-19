<?php

namespace App\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;

class PreviewEnglishScoreImportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'training_program_id' => 'required|integer|exists:training_programs,id',
            'file' => 'required|file|extensions:xlsx|mimes:xlsx|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'training_program_id' => __('english_scores.select_program'),
            'file.max' => __('english_scores.file_size'),
            'file' => __('english_scores.upload_xlsx'),
        ];
    }
}
