<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Feedback;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'evidence_id' =>null,
            'trainer_id' => null,
            'feedback' => fake()->sentence(),
            'status' => fake()->randomElement(TaskStatus::cases()),
        ];
    }
}
