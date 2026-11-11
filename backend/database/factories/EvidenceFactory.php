<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Evidence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evidence>
 */
class EvidenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => null,
            'evidence' => 'evidence/demo/' . fake()->uuid() . '.pdf',
        ];
    }
}
