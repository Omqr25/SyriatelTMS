<?php

namespace Database\Factories;

use App\Models\TrainingProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingProgram>
 */
class TrainingProgramFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = TrainingProgram::class;

    public function definition(): array
    {
        $applicationStart = fake()->dateTimeBetween('-1 month', 'now');
        $applicationEnd = fake()->dateTimeBetween($applicationStart, '+1 month');
        $startDate = fake()->dateTimeBetween($applicationEnd, '+1 month');
        $endDate = fake()->dateTimeBetween($startDate, '+3 months');

        return [
            'name' => fake()->randomElement([
                'Software Engineering Internship',
                'Backend Development Training',
                'Software Testing Training',
            ]),
            'description' => fake()->paragraph(),
            'application_start_date' => $applicationStart,
            'application_end_date' => $applicationEnd,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }
}
