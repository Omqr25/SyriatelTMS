<?php

namespace App\Services\Certificate;

use App\Models\CertificateTemplateVersion;
use App\Models\Trainee;
use App\Models\TrainingProgram;
use DomainException;
use Illuminate\Validation\ValidationException;

final class CertificatePreparation
{
    public function __construct(
        private CertificateNames $names,
        private CertificatePlaceholders $placeholders,
        private CertificateLayout $layout,
    ) {}

    public function snapshot(TrainingProgram $program, Trainee $trainee, CertificateTemplateVersion $version, string $date, string $number): array
    {
        $name = $this->names->resolve($program, $trainee);
        if ($name === null) {
            throw ValidationException::withMessages(['issuance' => __('certificates.english_name_required', ['trainee' => $trainee->id])]);
        }
        $configuration = $version->configuration;
        $values = $this->placeholders->resolve(
            $this->placeholders->used($configuration), $program, $trainee, $name['name'], $date, $number,
        );

        foreach ($configuration['boxes'] as $box) {
            try {
                if ($this->layout->box($box, $values, CertificateFontAssets::metrics())['fits']) {
                    continue;
                }
            } catch (DomainException) {
                // The renderer's font repertoire is stricter than generic Latin text validation.
            }
            throw ValidationException::withMessages(['configuration' => __('certificates.issuance_layout_invalid', ['trainee' => $trainee->id])]);
        }

        return ['trainee_name' => $name['name'], 'name_source' => $name['source'], 'values' => $values];
    }
}
