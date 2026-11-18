<?php
namespace Tests\Feature;

use App\Models\{User, Role, Trainee, Trainer, Department, Application, TrainingProgram, Program, Milestone, Task, Evidence, Feedback};
use App\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Notification, Storage};
use Tests\TestCase;

class TraineeTaskWorkflowTest extends TestCase
{
    use RefreshDatabase;
    private Trainee $trainee;
    private Trainer $trainer;
    private Task $task;
    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
        Notification::fake(); Storage::fake('private');
        $department = Department::factory()->create();
        $this->trainer = Trainer::factory()->create(['user_id' => $this->user('SUPERVISOR')->id, 'department_id' => $department->id]);
        $cycle = TrainingProgram::factory()->create();
        $application = Application::factory()->create(['training_program_id' => $cycle->id]);
        $this->trainee = Trainee::factory()->create(['user_id' => $this->user('TRAINEE')->id, 'trainer_id' => $this->trainer->id, 'department_id' => $department->id, 'application_id' => $application->id]);
        $program = Program::factory()->create(['trainer_id' => $this->trainer->id, 'department_id' => $department->id, 'training_program_id' => $cycle->id]);
        $phase = Milestone::factory()->create(['program_id' => $program->id]);
        $this->task = Task::factory()->create(['trainee_id' => $this->trainee->id, 'milestone_id' => $phase->id, 'deadline' => now()->addDays(2)]);
        $this->actingAs($this->trainee->user, 'api');
    }
    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['role' => $role])); return $user;
    }
    private function url(string $suffix = ''): string { return '/api/v1/task/tasks/'.$this->task->id.$suffix; }
    private function upload() { return $this->postJson($this->url('/evidence'), ['evidence' => UploadedFile::fake()->create('work.pdf', 10, 'application/pdf')]); }

    public function test_full_revision_cycle_preserves_history_and_notifies_supervisor(): void
    {
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.task.can_start', true);
        $this->postJson($this->url('/start'))->assertOk();
        $this->assertNotNull($this->task->fresh()->started_at);
        $this->postJson($this->url('/start'))->assertForbidden();
        $first = $this->upload()->assertOk()->json('data.id');
        $this->assertSame(TaskStatus::ForReview, $this->task->fresh()->status);
        $this->upload()->assertForbidden();
        $this->actingAs($this->trainer->user, 'api')->postJson($this->url('/feedback'), ['evidence_id' => $first, 'status' => 'need fix', 'feedback' => 'Please add the test results.'])->assertOk();
        $this->actingAs($this->trainee->user, 'api');
        $second = $this->upload()->assertOk()->json('data.id');
        $this->getJson($this->url())->assertOk()->assertJsonCount(2, 'data.task.evidences')
            ->assertJsonPath('data.task.evidences.0.feedback.feedback', 'Please add the test results.');
        $this->actingAs($this->trainer->user, 'api')->postJson($this->url('/feedback'), ['evidence_id' => $second, 'status' => 'completed', 'feedback' => 'Approved.'])->assertOk();
        $this->assertNotNull($this->task->fresh()->completed_at);
        $this->assertSame($this->trainer->id, Feedback::first()->trainer_id);
        $this->actingAs($this->trainee->user, 'api'); $this->upload()->assertForbidden();
        $this->get('/api/v1/task/'.$first.'/download')->assertOk();
        Notification::assertSentToTimes($this->trainer->user, \App\Notifications\TaskEvidenceSubmittedNotification::class, 2);
        $this->assertDatabaseCount('notifications', 4);
        $this->assertDatabaseCount('notifieds', 4);
        $this->assertSame(2, $this->trainee->user->notified()->count());
        $this->assertDatabaseHas('notifieds', ['notified_id' => $this->trainer->user_id]);
        $this->assertDatabaseCount('evidences', 2); $this->assertDatabaseCount('feedbacks', 2);
    }

    public function test_mixed_evidence_submits_as_one_review_and_downloads_each_file(): void
    {
        $this->postJson($this->url('/start'))->assertOk();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jR8sAAAAASUVORK5CYII=');
        $file = fn () => UploadedFile::fake()->createWithContent('screenshot.png', $png);
        $pdf = fn () => UploadedFile::fake()->create('report.pdf', 10, 'application/pdf');
        $url = $this->url('/evidence');

        $this->postJson($url, ['evidences' => [$pdf(), $file(), $file(), $file()]])->assertUnprocessable();
        $this->postJson($url, ['evidences' => [$file(), UploadedFile::fake()->create('data.txt', 1)]])->assertUnprocessable();
        $this->assertDatabaseCount('evidences', 0);

        $id = $this->postJson($url, ['evidences' => [$pdf(), $file(), $file()]])->assertOk()
            ->assertJsonCount(3, 'data.files')->assertJsonPath('data.files.1.name', 'screenshot.png')
            ->assertJsonMissingPath('data.files.1.path')->json('data.id');
        $this->assertDatabaseCount('evidences', 1);
        $this->assertSame(TaskStatus::ForReview, $this->task->fresh()->status);

        $this->actingAs($this->trainer->user, 'api');
        $download = '/api/v1/task/'.$id.'/download';
        $this->get($download)->assertOk();
        $this->get($download.'/1')->assertOk()->assertHeader('Content-Type', 'image/png');
        // App downloads get byte-identical data without an HTTP attachment response.
        $response = $this->getJson($download.'/1?format=json', ['Origin' => 'http://localhost:5173'])
            ->assertOk()->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeaderMissing('Content-Disposition')->assertJsonPath('mime_type', 'image/png');
        $this->assertSame($png, base64_decode($response->json('content'), true));
        $this->get($download.'/2')->assertOk();
        $this->get($download.'/3')->assertNotFound();
        $this->postJson($this->url('/feedback'), [
            'evidence_id' => $id, 'status' => 'need fix', 'feedback' => 'Please revise both files.',
        ])->assertOk();

        $this->actingAs($this->trainee->user, 'api');
        $this->postJson($url, ['evidences' => [$file()]])->assertOk()->assertJsonCount(1, 'data.files');
        $this->getJson($this->url())->assertOk()->assertJsonCount(2, 'data.task.evidences')
            ->assertJsonCount(3, 'data.task.evidences.0.files')
            ->assertJsonPath('data.task.evidences.0.feedback.feedback', 'Please revise both files.');

        // Submissions saved before the migration still use the original path.
        Evidence::findOrFail($id)->update(['files' => null]);
        $this->get($download)->assertOk();
    }

    public function test_team_visibility_does_not_allow_mutations_or_private_feedback(): void
    {
        $this->task->update(['status' => TaskStatus::IN_PROGRESS]); $id=$this->upload()->assertOk()->json('data.id');
        $peer = Trainee::factory()->create(['user_id' => $this->user('TRAINEE')->id, 'trainer_id' => $this->trainer->id,
            'department_id' => $this->trainee->department_id, 'application_id' => Application::factory()->create(['training_program_id' => $this->trainee->application->training_program_id])->id]);
        $this->actingAs($peer->user, 'api')->getJson($this->url())->assertOk()->assertJsonMissingPath('data.task.evidences')->assertJsonPath('data.task.can_submit', false);
        $this->postJson($this->url('/start'))->assertForbidden(); $this->upload()->assertForbidden();
        $this->getJson('/api/v1/task/'.$id.'/download')->assertForbidden();
        $this->getJson('/api/v1/task/'.$id.'/download?format=json')->assertForbidden();
        $this->getJson('/api/v1/myProgram')->assertOk()->assertJsonPath('data.phases.0.tasks.0.id', $this->task->id);
        $otherDepartment = Department::factory()->create();
        $otherTrainer = Trainer::factory()->create(['user_id' => $this->user('SUPERVISOR')->id, 'department_id' => $otherDepartment->id]);
        foreach (['trainer_id' => $otherTrainer->id, 'department_id' => $otherDepartment->id] as $field => $otherId) {
            $original = $peer->$field;
            // Changing either team association must revoke access, even in the same cycle.
            $peer->update([$field => $otherId]);
            $this->actingAs($peer->user->fresh(), 'api')->getJson($this->url())->assertForbidden();
            $peer->update([$field => $original]);
        }
        $peer->application->update(['training_program_id' => TrainingProgram::factory()->create()->id]);
        $this->actingAs($peer->user->fresh(), 'api')->getJson($this->url())->assertForbidden();
        $this->getJson('/api/v1/task/'.$this->task->milestone_id.'/tasks')->assertForbidden();
    }

    public function test_deadlines_inactive_accounts_and_upload_validation(): void
    {
        $this->upload()->assertForbidden();
        $this->postJson($this->url('/start'))->assertOk();
        foreach ([UploadedFile::fake()->create('bad.txt',1,'text/plain'), UploadedFile::fake()->create('large.pdf',10241,'application/pdf')] as $file) {
            $this->postJson($this->url('/evidence'), ['evidence'=>$file])->assertUnprocessable();
        }
        $this->task->update(['deadline'=>now()->subMinute()]); $this->upload()->assertForbidden();
        $this->task->update(['status'=>TaskStatus::PENDING]); $this->postJson($this->url('/start'))->assertForbidden();
        $this->task->update(['deadline'=>now()->addDay(), 'status'=>TaskStatus::NeedFix]);
        $this->upload()->assertUnprocessable();
        $this->task->update(['status'=>TaskStatus::PENDING]); $this->trainee->update(['status'=>'inactive']);
        $this->actingAs($this->trainee->user->fresh(),'api')->postJson($this->url('/start'))->assertForbidden();
        $this->assertDatabaseCount('evidences',0);
    }

    public function test_feedback_cannot_target_another_task_or_change_to_arbitrary_state(): void
    {
        $this->task->update(['status'=>TaskStatus::IN_PROGRESS]); $id=$this->upload()->assertOk()->json('data.id');
        $other=Task::factory()->create(['trainee_id'=>$this->trainee->id,'milestone_id'=>$this->task->milestone_id]);
        $foreign=Evidence::create(['task_id'=>$other->id,'evidence'=>'other.pdf']);
        $this->actingAs($this->trainer->user,'api')->postJson($this->url('/feedback'),['evidence_id'=>$foreign->id,'status'=>'completed','feedback'=>'Wrong task'])->assertUnprocessable();
        $this->postJson($this->url('/feedback'),['evidence_id'=>$id,'status'=>'pending','feedback'=>'Wrong state'])->assertUnprocessable();
        $this->assertDatabaseCount('feedbacks',0);
    }

    public function test_push_failure_does_not_undo_evidence_or_in_app_notification(): void
    {
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('Push unavailable'));
        $this->task->update(['status' => TaskStatus::IN_PROGRESS]);
        $id = $this->upload()->assertOk()->json('data.id');
        $this->assertSame(TaskStatus::ForReview, $this->task->fresh()->status);
        Storage::disk('private')->assertExists(Evidence::findOrFail($id)->evidence);
        $this->assertDatabaseHas('notifieds', ['notified_id' => $this->trainer->user_id]);
        $this->get('/api/v1/task/'.$id.'/download')->assertOk();
    }

    public function test_subtasks_are_saved_by_supervisor_and_visible_read_only_to_team(): void
    {
        $this->actingAs($this->trainer->user, 'api');
        $payload = ['name'=>'API exercise', 'priority'=>2, 'description'=>'Build an endpoint', 'deadline'=>now()->addDay()->toIso8601String(),
            'trainee_id'=>$this->trainee->id, 'instructions'=>'Follow the specification',
            'subtasks'=>[['title'=>'Write the API', 'completed'=>false], ['title'=>'Read the specification', 'completed'=>true]]];
        $id = $this->postJson('/api/v1/task/'.$this->task->milestone_id.'/tasks', $payload)->assertOk()->json('data.id');
        $this->actingAs($this->trainee->user, 'api')->getJson('/api/v1/task/tasks/'.$id)->assertOk()
            ->assertJsonPath('data.task.subtasks.1.completed', true);
        $this->putJson('/api/v1/task/tasks/'.$id, ['subtasks'=>[]])->assertForbidden();
        $this->actingAs($this->trainer->user, 'api')->putJson('/api/v1/task/tasks/'.$id, ['subtasks'=>[['title'=>'', 'completed'=>false]]])->assertUnprocessable();
        $this->putJson('/api/v1/task/tasks/'.$id, ['subtasks'=>[['title'=>'API done', 'completed'=>true]]])->assertOk()
            ->assertJsonPath('data.subtasks.0.completed', true);
    }

    public function test_supervisor_drafts_are_private_and_only_drafts_can_be_removed(): void
    {
        $phase = $this->task->milestone;
        $this->actingAs($this->trainer->user, 'api');
        $payload = ['name' => 'Draft exercise', 'deadline' => now()->addDay()->toIso8601String(), 'priority' => 2];
        $url = '/api/v1/task/'.$phase->id.'/tasks';
        $id = $this->postJson($url, $payload)->assertOk()->assertJsonPath('data.status', 'draft')->json('data.id');
        $draft = '/api/v1/task/tasks/'.$id;
        $this->getJson($draft)->assertOk()->assertJsonPath('data.task.can_delete', true);
        $this->deleteJson($this->url())->assertForbidden();
        $this->putJson($draft, ['name' => 'Draft exercise'])->assertUnprocessable();

        $this->actingAs($this->trainee->user, 'api');
        $this->getJson($draft)->assertForbidden();
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/myProgram')->assertOk()->assertJsonCount(1, 'data.phases.0.tasks');

        $this->actingAs($this->trainer->user, 'api');
        $this->putJson($draft, ['trainee_id' => $this->trainee->id])->assertOk()
            ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.can_delete', false);
        $this->deleteJson($draft)->assertForbidden();
        $other = $this->postJson($url, $payload)->assertOk()->json('data.id');
        $this->deleteJson('/api/v1/task/tasks/'.$other)->assertOk();
        $this->assertDatabaseMissing('tasks', ['id' => $other]);

        $this->actingAs($this->trainee->user, 'api')->postJson($draft.'/start')->assertOk();
    }

    public function test_bulk_assignments_are_independent_and_reject_ineligible_trainees_atomically(): void
    {
        $phase = $this->task->milestone;
        $phase->program->update(['deadline' => now()->addDays(4)]);
        $peer = Trainee::factory()->create([
            'user_id' => $this->user('TRAINEE')->id,
            'department_id' => $this->trainee->department_id,
            'trainer_id' => $this->trainer->id,
            'application_id' => Application::factory()->create([
                'training_program_id' => $phase->program->training_program_id,
            ])->id,
        ]);
        $this->actingAs($this->trainer->user, 'api');
        $url = '/api/v1/task/'.$phase->id.'/tasks';
        $payload = ['name' => 'Shared exercise', 'deadline' => now()->addDay()->toIso8601String(),
            'priority' => 1, 'trainee_ids' => [$this->trainee->id, $peer->id]];
        $this->getJson('/api/v1/program/'.$phase->program_id.'/task-candidates')
            ->assertOk()->assertJsonCount(2, 'data');
        $ids = $this->postJson($url, $payload)->assertOk()->assertJsonCount(2, 'data')->json('data.*.id');
        $this->assertNotSame($ids[0], $ids[1]);
        $peer->update(['status' => 'inactive']);
        $this->postJson($url, $payload)->assertUnprocessable();
        $this->assertDatabaseCount('tasks', 3);
        $this->putJson($this->url(), ['trainee_id' => $peer->id])->assertUnprocessable();
        $this->putJson($this->url(), ['deadline' => now()->addDays(5)->toIso8601String()])->assertUnprocessable();
        $this->putJson($this->url(), ['deadline' => 'invalid'])->assertUnprocessable();
        $this->task->update(['status' => TaskStatus::COMPLETED]);
        $this->putJson($this->url(), ['name' => 'New name'])->assertForbidden();
        $this->deleteJson($this->url())->assertForbidden();
        $this->actingAs($this->trainee->user, 'api')->postJson('/api/v1/task/tasks/'.$ids[0].'/start')->assertOk();
        $this->assertSame(TaskStatus::PENDING, Task::findOrFail($ids[1])->status);
    }

    public function test_program_creation_saves_staged_tasks_and_phase_updates_preserve_them(): void
    {
        $phase = $this->task->milestone;
        $program = $phase->program;
        $this->actingAs($this->trainer->user, 'api');
        $existing = '/api/v1/program/'.$program->id;
        $this->putJson($existing, ['phases' => [['id' => $phase->id, 'name' => 'Renamed', 'description' => null]]])
            ->assertOk();
        $this->assertDatabaseHas('tasks', ['id' => $this->task->id, 'milestone_id' => $phase->id]);
        $this->putJson($existing, ['phases' => []])->assertUnprocessable();
        $this->deleteJson($existing)->assertUnprocessable();

        TrainingProgram::factory()->create(['end_date' => now()->addDays(30), 'created_at' => now()->addSecond()]);
        $task = ['name' => 'Staged draft', 'deadline' => now()->addDays(2)->toIso8601String(), 'priority' => 1, 'trainee_ids' => []];
        $payload = ['title' => 'New program', 'description' => 'Training tasks', 'deadline' => now()->addDays(4)->toDateString(),
            'phases' => [['name' => 'First phase', 'tasks' => [$task]]]];
        $invalid = $payload;
        $invalid['phases'][0]['tasks'][0]['deadline'] = now()->addDays(5)->toIso8601String();
        $this->postJson('/api/v1/program', $invalid)->assertUnprocessable();
        $this->assertDatabaseMissing('programs', ['title' => 'New program']);
        $this->postJson('/api/v1/program', $payload)->assertOk()
            ->assertJsonPath('data.phases.0.tasks.0.status', 'draft');
    }

    public function test_overdue_jobs_preserve_pending_review_and_approved_tasks(): void
    {
        foreach ([TaskStatus::ForReview,TaskStatus::COMPLETED,TaskStatus::CANCELLED,TaskStatus::IN_PROGRESS] as $status) {
            $this->task->update(['status'=>$status,'deadline'=>now()->subMinute()]);
            (new \App\Jobs\MarkTaskAsOverdueJob($this->task))->handle();
            $this->assertSame($status===TaskStatus::IN_PROGRESS ? TaskStatus::OVERDUE : $status,$this->task->fresh()->status);
        }
        $this->task->update(['status'=>TaskStatus::COMPLETED]);
        $this->artisan('app:update-overdue-tasks')->assertSuccessful();
        $this->assertSame(TaskStatus::COMPLETED,$this->task->fresh()->status);
    }
}
