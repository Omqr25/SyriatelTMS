<?php

namespace Database\Factories;

use App\Models\VacationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VacationRequest>
 */
class VacationRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = VacationRequest::class;

    public function definition(): array
    {
        $start_date = fake()->dateTimeBetween('now', '+1 month');

        $end_date = fake()->dateTimeBetween( $start_date,
            (clone $start_date)->modify('+7 days')
        );

        return [
            'reason' => fake()->sentence(),
            'start_date' => $start_date,
            'end_date' =>$end_date,
            'trainee_id' => null,
            'trainer_id' => null,
        ];
    }
}
