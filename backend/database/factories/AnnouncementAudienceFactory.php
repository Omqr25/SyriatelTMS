<?php

namespace Database\Factories;

use App\Models\AnnouncementAudience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnouncementAudience>
 */
class AnnouncementAudienceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = AnnouncementAudience::class;

    public function definition(): array
    {
        return [
            'announcement_id' => null,
            'audience_id' => null,
            'read' => fake()->boolean(30),
        ];
    }
}
