<?php

namespace Database\Factories;

use App\Models\EvidenceResubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvidenceResubmission>
 */
class EvidenceResubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feedback_id' => null,
            'resubmitted_evidence_id' => null,
        ];
    }
}
