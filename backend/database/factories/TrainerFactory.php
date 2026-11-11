<?php

namespace Database\Factories;

use App\Models\Trainer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trainer>
 */
class TrainerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'department_id' => null,
        ];
    }

    public function forUser($user): static
    {
        return $this->state([
            'user_id' => $user->id,
        ]);
    }

    public function forDepartment($department): static
    {
        return $this->state([
            'department_id' => $department->id,
        ]);
    }
}
