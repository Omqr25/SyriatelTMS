<?php

namespace Database\Factories;

use App\Models\Absence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Absence>
 */
class AbsenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Absence::class;

    public function definition(): array
    {
        return [
            'trainee_id' => null,
            'trainer_id' => null,
            'reason' => fake()->sentence(),
            'date' => fake()->dateTimeBetween('-1 month', 'now'),
            'request_id' => null,
        ];
    }
}
