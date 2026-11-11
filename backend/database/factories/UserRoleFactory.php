<?php

namespace Database\Factories;

use App\Models\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserRole>
 */
class UserRoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = UserRole::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'role_id' => null,
            'assigned_at' => fake()->dateTimeBetween('-2 months', 'now'),
        ];
    }
}
