<?php

namespace Tests\Feature;

use App\Jobs\SendInterviewEmailJob;
use App\Models\Application;
use App\Models\Department;
use App\Models\Interview;
use App\Models\Role;
use App\Models\Trainer;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Notifications\TmsPushNotification;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InterviewSchedulingTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
        $hr = User::factory()->create();
        $hr->roles()->attach(Role::firstOrCreate(['role' => 'HR']));
        $this->actingAs($hr, 'api');
        Queue::fake();
        Notification::fake();
    }

    private function trainer(): Trainer
    {
        return Trainer::factory()->create([
            'department_id' => Department::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function application(Trainer $trainer): Application
    {
        $application = Application::factory()->forProgram(TrainingProgram::factory()->create())->create(['status' => 'qualified']);
        $application->assigns()->create(['trainer_id' => $trainer->id, 'status' => 'assigned']);

        return $application;
    }

    public function test_scheduling_links_the_appointment_changes_status_and_triggers_notifications(): void
    {
        $trainer = $this->trainer();
        $application = $this->application($trainer);
        $time = now()->addDay()->setTime(10, 0)->toDateTimeString();
        $this->postJson("/api/v1/application/{$application->id}/interview", [
            'trainer_id' => $trainer->id, 'time' => $time, 'location' => 'HQ meeting room',
        ])->assertOk();
        $interview = $application->interviews()->firstOrFail();
        $this->assertSame($trainer->id, $interview->trainer_id);
        $this->assertSame($time, $interview->time->toDateTimeString());
        $this->assertSame('HQ meeting room', $interview->location);
        $this->assertSame('for interview', $application->fresh()->status->value);
        Queue::assertPushed(SendInterviewEmailJob::class, fn ($job) => $job->application->id === $application->id && $job->interview->id === $interview->id);
        Notification::assertSentTo($trainer->user, TmsPushNotification::class, fn ($notification) => $notification->data['application_id'] === $application->id && $notification->data['interview_id'] === $interview->id);
    }

    public function test_conflict_for_the_same_supervisor_does_not_change_status_or_send_notifications(): void
    {
        $trainer = $this->trainer();
        $application = $this->application($trainer);
        $other = $this->application($trainer);
        $time = now()->addDay()->setTime(10, 0);
        Interview::create(['application_id' => $other->id, 'trainer_id' => $trainer->id, 'time' => $time, 'location' => 'Other room']);
        $this->postJson("/api/v1/application/{$application->id}/interview", [
            'trainer_id' => $trainer->id, 'time' => $time->copy()->addMinutes(30)->toDateTimeString(), 'location' => 'HQ',
        ])->assertStatus(400);
        $this->assertSame('qualified', $application->fresh()->status->value);
        $this->assertSame(0, $application->interviews()->count());
        Queue::assertNothingPushed();
        Notification::assertNothingSent();
    }

    public function test_different_supervisors_can_have_the_same_appointment_time(): void
    {
        $trainer = $this->trainer();
        $otherTrainer = $this->trainer();
        $application = $this->application($trainer);
        $other = $this->application($otherTrainer);
        $time = now()->addDay()->setTime(10, 0)->toDateTimeString();
        Interview::create(['application_id' => $other->id, 'trainer_id' => $otherTrainer->id, 'time' => $time, 'location' => 'Other room']);
        $this->postJson("/api/v1/application/{$application->id}/interview", [
            'trainer_id' => $trainer->id, 'time' => $time, 'location' => 'HQ',
        ])->assertOk();
    }
}
