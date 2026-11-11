<?php

namespace Database\Factories;

use App\Models\Interview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interview>
 */
class InterviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Interview::class;

    public function definition(): array
    {
        return [
            'application_id' => null,
            'trainer_id' => null,
            'time' => fake()->dateTimeBetween('now', '+1 month'),
            'location' => fake()->randomElement([
                'Syriatel HQ - Meeting Room 1',
                'Syriatel HQ - Meeting Room 2',
                'Online Meeting',
            ]),
            'evaluation' => fake()->randomFloat(2, 50, 100),
            'interview_note' => fake()->paragraph(),
        ];
    }
}
