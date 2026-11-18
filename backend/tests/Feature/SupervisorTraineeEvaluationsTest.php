<?php

namespace Tests\Feature;

use App\Models\{Application, Department, Evaluation, Role, Trainee, Trainer, TrainingProgram, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupervisorTraineeEvaluationsTest extends TestCase
{
    use RefreshDatabase;

    private Trainer $trainer;
    private TrainingProgram $program;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
        $this->program = TrainingProgram::factory()->create(['status' => 'Open']);
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['role' => 'SUPERVISOR']));
        $this->trainer = Trainer::factory()->create([
            'user_id' => $user->id,
            'department_id' => Department::factory()->create()->id,
        ]);
        $this->actingAs($user, 'api');
    }

    private function trainee(?Trainer $trainer = null, array $attributes = []): Trainee
    {
        $trainer ??= $this->trainer;
        return Trainee::factory()->create(array_merge([
            'user_id' => User::factory()->create()->id,
            'trainer_id' => $trainer->id,
            'department_id' => $trainer->department_id,
            'application_id' => Application::factory()->create(['training_program_id' => $this->program->id])->id,
        ], $attributes));
    }

    private function evaluate(Trainee $trainee, float $score = 82.5): Evaluation
    {
        $evaluation = Evaluation::factory()->create([
            'trainee_id' => $trainee->id,
            'trainer_id' => $this->trainer->id,
            'score' => $score,
            'description' => 'Overall feedback',
        ]);
        $evaluation->criteria()->create(['name' => 'Attendance', 'score' => $score, 'notice' => 'Attendance feedback']);
        return $evaluation;
    }

    public function test_list_returns_score_and_complete_details_or_null_in_one_response(): void
    {
        $evaluated = $this->trainee();
        $evaluation = $this->evaluate($evaluated, 0);
        $unevaluated = $this->trainee();

        foreach (range(1, 2) as $attempt) {
            $response = $this->getJson('/api/v1/trainee/trainer')->assertOk()->assertJsonCount(2, 'data');
            $rows = collect($response->json('data'))->keyBy('id');
            $this->assertSame('0.00', $rows[$evaluated->id]['evaluation']['score']);
            $this->assertSame($evaluation->id, $rows[$evaluated->id]['evaluation']['id']);
            $this->assertSame('Overall feedback', $rows[$evaluated->id]['evaluation']['description']);
            $this->assertSame($evaluation->created_at->format('d-m-Y H:i'), $rows[$evaluated->id]['evaluation']['evaluation_date']);
            $this->assertSame('Attendance feedback', $rows[$evaluated->id]['evaluation']['indicators'][0]['notice']);
            $this->assertNull($rows[$unevaluated->id]['evaluation']);
            $this->assertArrayNotHasKey('trainee', $rows[$evaluated->id]['evaluation']);
        }
    }

    public function test_list_preserves_assignment_and_active_trainee_scope(): void
    {
        $mine = $this->trainee();
        $otherTrainer = Trainer::factory()->create([
            'user_id' => User::factory()->create()->id,
            'department_id' => $this->trainer->department_id,
        ]);
        $this->evaluate($this->trainee($otherTrainer));
        $this->trainee(attributes: ['status' => 'completed']);
        $this->getJson('/api/v1/trainee/trainer')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_query_count_does_not_grow_with_the_number_of_trainees(): void
    {
        $this->evaluate($this->trainee());
        $countQueries = function (): int {
            $this->actingAs(User::find($this->trainer->user_id), 'api');
            DB::enableQueryLog();
            DB::flushQueryLog();
            try {
                $this->getJson('/api/v1/trainee/trainer')->assertOk();
                return count(DB::getQueryLog());
            } finally {
                DB::disableQueryLog();
            }
        };
        $oneTrainee = $countQueries();
        foreach (range(1, 8) as $index) {
            $this->evaluate($this->trainee());
        }
        $this->assertSame($oneTrainee, $countQueries(), 'Evaluation and indicator relations must be eager loaded.');
    }

    public function test_hr_trainee_response_is_unchanged(): void
    {
        $this->evaluate($this->trainee());
        $hr = User::factory()->create();
        $hr->roles()->attach(Role::firstOrCreate(['role' => 'HR']));
        $this->actingAs($hr, 'api')->getJson('/api/v1/trainee')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.evaluation');
    }
}
