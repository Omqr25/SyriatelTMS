<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Enums\City;
use App\Enums\FieldOfStudy;
use App\Enums\HowHeard;
use App\Enums\YearOfStudy;
use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'training_program_id' => null,
            'national_id_number'=>fake()->unique()->numerify('###########'),
//            'national_id_number' => fake()->unique()->numberBetween(10000000000, 99999999999),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone_number' => fake()->phoneNumber(),
            'birth_date' => fake()->dateTimeBetween('-30 years', '-18 years'),

            'city' => fake()->randomElement(City::cases()),
            'home_address' => fake()->address(),

            'university' => fake()->randomElement([
                'Damascus University',
                'Tishreen University',
                'Aleppo University',
                'Al-Baath University',
            ]),

            'field_of_study' => fake()->randomElement(FieldOfStudy::cases()),
            'academic_major' => fake()->randomElement([
                'Software Engineering',
                'Computer Science',
                'Information Technology',
            ]),

            'year_of_study' => fake()->randomElement(YearOfStudy::cases()),
            'gpa' => fake()->numberBetween(0,100),

            'technical_skills' => fake()->sentence(),
            'academic_qualification' => 'Bachelor Student',

            'cv' => 'cv/sample-cv.pdf',
            'national_id' => fake()->numerify('##########'),
            'personal_photo' => 'photos/default.jpg',

            'commitment' => true,
            'previous_training' => false,

            'how_heard' => fake()->randomElement(HowHeard::cases()),
            'additional_info' => fake()->optional()->paragraph(),

            'status' => fake()->randomElement(ApplicationStatus::cases()),
        ];
    }

    public function accepted(): static
    {
        return $this->state([
            'status' => ApplicationStatus::ACCEPTED,
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => ApplicationStatus::REJECTED,
        ]);
    }

    public function forProgram($program): static
    {
        return $this->state([
            'training_program_id' => $program->id,
        ]);
    }
}
