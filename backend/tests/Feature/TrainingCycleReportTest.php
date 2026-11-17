<?php

namespace Tests\Feature;

use App\Models\{User, Role, TrainingProgram};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingCycleReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['role' => 'HR']));
        $this->actingAs($user, 'api');
    }

    public function test_statistics_can_be_reloaded_without_generating_a_file(): void
    {
        $program = TrainingProgram::factory()->create(['status' => 'Open']);
        foreach (range(1, 2) as $attempt) {
            $this->getJson('/api/v1/reports/training_cycle')->assertOk()
                ->assertJsonPath('data.training_program.id', $program->id)
                ->assertJsonPath('data.total_applications', 0)
                ->assertJsonStructure(['data' => ['generated_at', 'completed_trainees', 'total_departments']]);
        }
    }

    public function test_no_open_program_returns_a_useful_not_found_response(): void
    {
        $this->getJson('/api/v1/reports/training_cycle')->assertNotFound()
            ->assertJsonPath('message', __('reports.no_program'));
    }
}
