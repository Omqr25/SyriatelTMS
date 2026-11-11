<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'deadline' => fake()->dateTimeBetween('now', '+2 months'),
            'milestone_id' => null,
            'priority'=>rand(1,10),
            'instructions'=> fake()->paragraph(),
            'trainee_id' => null,
            'status' => TaskStatus::PENDING,
        ];
    }

    public function inProgress(): static
    {
        return $this->state([
            'status' => TaskStatus::IN_PROGRESS,
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => TaskStatus::COMPLETED,
        ]);
    }
}
