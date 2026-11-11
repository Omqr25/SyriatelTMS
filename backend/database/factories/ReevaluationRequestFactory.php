<?php

namespace Database\Factories;

use App\Enums\EvaluationRequestStatus;
use App\Models\ReevaluationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReevaluationRequest>
 */
class ReevaluationRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = ReevaluationRequest::class;

    public function definition(): array
    {
        return [
            'reason' => fake()->paragraph(),
            'trainee_id' => null,
            'trainer_id' => null,
            'evaluation_id' => null,
            'status'=>fake()->randomElement(EvaluationRequestStatus::cases()),
            'decision_note' => fake()->paragraph(),
        ];
    }
}
