<?php

namespace Database\Factories;

use App\Enums\City;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Software Engineering',
                'Network Engineering',
                'Human Resources',
                'Finance',
                'Marketing',
                'Customer Service',
            ]),

            'location' => fake()->randomElement(City::cases())->value,

        ];
    }
}
