<?php

namespace Database\Factories;

use App\Models\Milestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Milestone>
 */
class MilestoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Milestone::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Requirements Analysis',
                'System Design',
                'Backend Development',
                'Frontend Development',
                'Testing',
            ]),
            'description' => fake()->paragraph(),
            'program_id' => null,
        ];
    }
}
