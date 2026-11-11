<?php

namespace Database\Factories;

use App\Enums\TraineeStatus;
use App\Models\Trainee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trainee>
 */
class TraineeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Trainee::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'status' => TraineeStatus::ACTIVE,
            'department_id' => null,
            'application_id' => null,
        ];
    }
}
