<?php

namespace Database\Factories;

use App\Models\Criteria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Criteria>
 */
class CriteriaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Criteria::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Teamwork',
                'Attendance',
                'Progress',
            ]),
            'score' => fake()->randomFloat(2, 50, 100),
            'evaluation_id' => null,
        ];
    }
}
