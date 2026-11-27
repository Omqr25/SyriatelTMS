<?php

namespace App\Services\Certificate;

use App\Models\Trainee;
use App\Models\TrainingProgram;
use Illuminate\Validation\ValidationException;

final class CertificatePlaceholders
{
    public const ACTIVE = [
        'trainee_name', 'program_name', 'department_name', 'program_start_date',
        'program_end_date', 'issue_date', 'certificate_number',
    ];

    public const RESERVED = ['unit_name', 'salutation'];

    public function used(array $configuration): array
    {
        $used = [];
        foreach ($configuration['boxes'] as $box) {
            foreach ($box['paragraphs'] as $paragraph) {
                foreach ($paragraph as $node) {
                    if (array_key_exists('token', $node)) {
                        $used[$node['token']] = true;
                    }
                }
            }
        }

        return array_keys($used);
    }

    public function resolve(array $used, TrainingProgram $program, Trainee $trainee, string $name, string $issueDate, string $number): array
    {
        $values = [];
        foreach ($used as $token) {
            $value = match ($token) {
                'trainee_name' => $name,
                'program_name' => $program->name,
                'department_name' => $trainee->department?->name,
                'program_start_date' => $program->start_date?->toDateString(),
                'program_end_date' => $program->end_date?->toDateString(),
                'issue_date' => $issueDate,
                'certificate_number' => $number,
                default => null,
            };
            if (! is_string($value) || trim($value) === '' || ! CertificateText::isEnglishText($value)) {
                throw ValidationException::withMessages([
                    'placeholders' => __('certificates.invalid_placeholder_value', ['placeholder' => $token, 'trainee' => $trainee->id]),
                ]);
            }
            $values[$token] = $value;
        }

        return $values;
    }
}
