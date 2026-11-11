<?php

namespace Database\Factories;

use App\Models\Notified;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notified>
 */
class NotifiedFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Notified::class;

    public function definition(): array
    {
        return [
            'notification_id' => null,
            'notified_id' => null,
            'read' => fake()->boolean(30),
        ];
    }
}
