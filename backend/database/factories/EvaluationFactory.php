<?php

namespace Database\Factories;

use App\Models\Evaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evaluation>
 */
class EvaluationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Evaluation::class;

    public function definition(): array
    {
        return [
            'score' => fake()->randomFloat(2, 50, 100),
            'description' => fake()->paragraph(),
            'trainee_id' => null,
            'trainer_id' => null,
        ];
    }
}
