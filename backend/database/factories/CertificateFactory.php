<?php

namespace Database\Factories;

use App\Models\Certificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Certificate::class;

    public function definition(): array
    {
        return [
            'path' => 'certificates/' . fake()->uuid() . '.pdf',
            'trainee_id' => null,
            'training_program_id' => null,
            'issue_date' => now()->toDateString(),
        ];
    }
}
