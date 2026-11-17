<?php

namespace Tests\Feature;

use App\Enums\{ApplicationStatus, City, FieldOfStudy, YearOfStudy};
use App\Models\{Application, TrainingProgram};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantGpaRangeTest extends TestCase
{
    use RefreshDatabase;

    private function applicant(array $attributes = []): Application
    {
        return Application::factory()->create(array_merge([
            'training_program_id' => TrainingProgram::factory()->create()->id,
            'full_name' => 'Range Candidate',
            'status' => ApplicationStatus::PENDING,
            'city' => City::DAMASCUS,
            'field_of_study' => FieldOfStudy::IT->value,
            'year_of_study' => YearOfStudy::FOURTH,
        ], $attributes));
    }

    public function test_range_is_inclusive_and_supports_decimal_gpas(): void
    {
        foreach ([59.99, 60, 75.25, 90, 90.01] as $gpa) {
            $this->applicant(['gpa' => $gpa]);
        }

        $this->assertEquals([60, 75.25, 90], Application::filter([
            'gpa_min' => '60', 'gpa_max' => '90',
        ])->orderBy('gpa')->pluck('gpa')->all());
    }

    public function test_zero_upper_bound_and_default_range_are_respected(): void
    {
        foreach ([0, 50, 100] as $gpa) {
            $this->applicant(['gpa' => $gpa]);
        }

        $this->assertSame(3, Application::filter(['gpa_min' => 0, 'gpa_max' => 100])->count());
        $this->assertEquals([0], Application::filter(['gpa_min' => '0', 'gpa_max' => '0'])->pluck('gpa')->all());
        $this->assertSame(2, Application::filter(['gpa_min' => 50])->count());
        $this->assertSame(2, Application::filter(['gpa_max' => 50])->count());
        $this->assertSame(0, Application::filter(['gpa_min' => 90, 'gpa_max' => 60])->count());
        $this->assertSame(3, Application::filter(['gpa_min' => '', 'gpa_max' => ''])->count());
        $this->assertSame(2, Application::filter(['gpa' => 50])->count());
    }

    public function test_range_combines_with_all_existing_applicant_filters(): void
    {
        $match = $this->applicant(['gpa' => 80]);
        foreach ([
            ['gpa' => 95],
            ['city' => City::ALEPPO],
            ['status' => ApplicationStatus::ASSIGNED],
            ['field_of_study' => FieldOfStudy::Civil->value],
            ['year_of_study' => YearOfStudy::FIRST],
            ['full_name' => 'Other Candidate'],
        ] as $different) {
            $this->applicant(array_merge(['gpa' => 80], $different));
        }

        $results = Application::filter([
            'search' => 'Range Candidate',
            'status' => ApplicationStatus::PENDING->value,
            'city' => City::DAMASCUS->value,
            'field_of_study' => FieldOfStudy::IT->value,
            'year_of_study' => YearOfStudy::FOURTH->value,
            'gpa_min' => 75, 'gpa_max' => 85,
        ])->paginate(10);

        $this->assertSame(1, $results->total());
        $this->assertSame($match->id, $results->items()[0]->id);
    }
}
