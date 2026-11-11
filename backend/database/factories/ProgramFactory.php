<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Program::class;

    public function definition(): array
    {
        return [
            'title' => fake()->randomElement([
                'Backend Development',
                'Frontend Development',
                'Software Testing',
                'Database Development',
            ]),
            'description' => fake()->paragraph(),
            'department_id' => null,
            'trainer_id' => null,
            'training_program_id' => null,
        ];
    }
}
