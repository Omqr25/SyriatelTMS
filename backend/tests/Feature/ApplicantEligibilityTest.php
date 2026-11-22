<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\City;
use App\Models\Application;
use App\Models\Role;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ApplicantEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/application';

    private ?TrainingProgram $program = null;

    private function signIn(string $role = 'HR'): void
    {
        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['role' => $role]));
        $this->actingAs($user, 'api');
    }

    private function applicant(array $values): Application
    {
        $this->program ??= TrainingProgram::factory()->create();

        return Application::factory()->forProgram($this->program)->create($values + [
            'full_name' => 'Matching Candidate',
            'status' => ApplicationStatus::PENDING,
            'city' => City::DAMASCUS,
        ]);
    }

    private function results(array $query): TestResponse
    {
        return $this->getJson(self::ENDPOINT.'?'.http_build_query($query));
    }

    public function test_filters_distinguish_boundaries_zero_and_unrecorded_scores(): void
    {
        $this->signIn();
        $atMinimum = $this->applicant(['gpa' => 70, 'english_test_score' => 45]);
        $belowMinimum = $this->applicant(['gpa' => 69.99, 'english_test_score' => 44.99]);
        $pending = $this->applicant(['gpa' => 85, 'english_test_score' => null]);
        $zero = $this->applicant(['gpa' => 0, 'english_test_score' => 0]);
        $this->applicant(['gpa' => 72, 'english_test_score' => 49, 'city' => City::ALEPPO]);
        $this->applicant(['gpa' => 72, 'english_test_score' => 49, 'full_name' => 'Other Applicant']);
        $this->applicant(['gpa' => 72, 'english_test_score' => 49, 'status' => ApplicationStatus::ASSIGNED]);
        $base = ['search' => 'Matching', 'city' => City::DAMASCUS->value, 'status' => 'pending'];

        $this->assertSame([$pending->id, $atMinimum->id], $this->results($base + [
            'gpa_eligibility' => 'eligible',
        ])->assertOk()->json('data.*.id'));
        $this->assertSame([$atMinimum->id], $this->results($base + [
            'gpa_eligibility' => 'eligible', 'english_eligibility' => 'eligible',
        ])->assertOk()->json('data.*.id'));
        $this->assertSame([$zero->id, $belowMinimum->id], $this->results($base + [
            'english_eligibility' => 'not_eligible',
        ])->assertOk()->json('data.*.id'));
        $this->assertSame([$pending->id], $this->results($base + [
            'gpa_eligibility' => 'eligible', 'english_eligibility' => 'pending',
        ])->assertOk()->json('data.*.id'));
        $this->results($base + ['gpa_eligibility' => 'not_eligible', 'english_eligibility' => 'pending'])
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_sorting_precedes_pagination_and_missing_english_scores_stay_last(): void
    {
        $this->signIn();
        $first = $this->applicant(['full_name' => 'Alpha', 'gpa' => 72, 'english_test_score' => 55]);
        $second = $this->applicant(['full_name' => 'Beta', 'gpa' => 81, 'english_test_score' => 45, 'status' => ApplicationStatus::QUALIFIED]);
        $pending = $this->applicant(['full_name' => 'Gamma', 'gpa' => 71, 'english_test_score' => null]);
        $third = $this->applicant(['full_name' => 'Delta', 'gpa' => 78, 'english_test_score' => 55, 'status' => ApplicationStatus::ASSIGNED]);
        $this->applicant(['full_name' => 'Excluded', 'gpa' => 68, 'english_test_score' => 90]);
        $base = ['gpa_eligibility' => 'eligible'];

        $ascending = $base + ['sort_by' => 'english_test_score', 'sort_direction' => 'asc', 'limit' => 2];
        $this->assertSame([$second->id, $third->id], $this->results($ascending)
            ->assertOk()->assertJsonPath('meta.total', 4)->json('data.*.id'));
        $this->assertSame([$first->id, $pending->id], $this->results($ascending + ['page' => 2])
            ->assertOk()->json('data.*.id'));
        $this->assertSame([$third->id, $first->id, $second->id, $pending->id], $this->results($base + [
            'sort_by' => 'english_test_score', 'sort_direction' => 'desc',
        ])->assertOk()->json('data.*.id'));
        $this->assertSame([$pending->id, $first->id, $third->id, $second->id], $this->results($base + [
            'sort_by' => 'status',
        ])->assertOk()->json('data.*.id'));
        $this->assertSame([$first->id, $second->id, $third->id, $pending->id], $this->results($base + [
            'sort_by' => 'name',
        ])->assertOk()->json('data.*.id'));
        $this->assertSame([$second->id, $third->id, $first->id, $pending->id], $this->results($base + [
            'sort_by' => 'gpa', 'sort_direction' => 'desc',
        ])->assertOk()->json('data.*.id'));
    }

    public function test_sort_and_filter_options_are_validated_on_the_hr_endpoint(): void
    {
        $this->signIn('SUPERVISOR');
        $this->results(['sort_by' => 'name'])->assertForbidden();

        $this->signIn();
        $this->results([
            'sort_by' => 'email', 'sort_direction' => 'other',
            'gpa_eligibility' => 'pending', 'english_eligibility' => 'unknown',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'sort_by', 'sort_direction', 'gpa_eligibility', 'english_eligibility',
        ]);
    }
}
