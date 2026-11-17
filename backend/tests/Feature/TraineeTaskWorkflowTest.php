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
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseCount('notifieds', 2);
        $this->assertDatabaseHas('notifieds', ['notified_id' => $this->trainer->user_id]);
        $this->assertDatabaseCount('evidences', 2); $this->assertDatabaseCount('feedbacks', 2);
    }

    public function test_team_visibility_does_not_allow_mutations_or_private_feedback(): void
    {
        $this->task->update(['status' => TaskStatus::IN_PROGRESS]); $id=$this->upload()->assertOk()->json('data.id');
        $peer = Trainee::factory()->create(['user_id' => $this->user('TRAINEE')->id, 'trainer_id' => $this->trainer->id,
            'department_id' => $this->trainee->department_id, 'application_id' => Application::factory()->create(['training_program_id' => $this->trainee->application->training_program_id])->id]);
        $this->actingAs($peer->user, 'api')->getJson($this->url())->assertOk()->assertJsonMissingPath('data.task.evidences')->assertJsonPath('data.task.can_submit', false);
        $this->postJson($this->url('/start'))->assertForbidden(); $this->upload()->assertForbidden();
        $this->getJson('/api/v1/task/'.$id.'/download')->assertForbidden();
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
        $payload = ['name'=>'API exercise', 'description'=>'Build an endpoint', 'deadline'=>now()->addDay()->toIso8601String(),
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
