<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationAssign;
use App\Models\Trainer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationAssign>
 */
class ApplicationAssignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trainer_id' => Trainer::inRandomOrder()->first()->id,
            'application_id' => Application::inRandomOrder()->first()->id,
            'status' => fake()->randomElement(ApplicationStatus::cases()),
        ];
    }
}
