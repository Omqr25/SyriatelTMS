<?php

namespace Tests\Feature;

use App\Jobs\SendInterviewEmailJob;
use App\Mail\InterviewScheduledMail;
use App\Models\Application;
use App\Models\Department;
use App\Models\Interview;
use App\Models\Trainer;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class InterviewEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function appointment(): array
    {
        $trainer = Trainer::factory()->create([
            'user_id' => User::factory()->create(['email' => 'supervisor@example.org'])->id,
            'department_id' => Department::factory()->create(['name' => 'Engineering'])->id,
        ]);
        $application = Application::factory()->forProgram(TrainingProgram::factory()->create())->create(['full_name' => 'Applicant Name', 'email' => 'applicant@gmail.com']);
        $interview = Interview::create(['application_id' => $application->id, 'trainer_id' => $trainer->id, 'time' => '2026-12-01 10:30:00', 'location' => 'HQ room 2']);

        return [$application, $interview];
    }

    public function test_both_independent_jobs_address_only_their_intended_recipient(): void
    {
        Mail::fake();
        [$application, $interview] = $this->appointment();
        (new SendInterviewEmailJob($application, $interview, 'applicant'))->handle();
        (new SendInterviewEmailJob($application, $interview, 'supervisor'))->handle();
        Mail::assertSent(InterviewScheduledMail::class, fn ($mail) => $mail->hasTo('applicant@gmail.com') && ! $mail->hasTo('supervisor@example.org'));
        Mail::assertSent(InterviewScheduledMail::class, fn ($mail) => $mail->hasTo('supervisor@example.org') && ! $mail->hasTo('applicant@gmail.com'));
        Mail::assertSentCount(2);
    }

    public function test_supervisor_transport_failure_does_not_prevent_applicant_delivery(): void
    {
        [$application, $interview] = $this->appointment();
        $failed = Mockery::mock(PendingMail::class);
        $failed->shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP rejected recipient'));
        $successful = Mockery::mock(PendingMail::class);
        $successful->shouldReceive('send')->once()->with(Mockery::type(InterviewScheduledMail::class));
        Mail::shouldReceive('to')->with('supervisor@example.org')->once()->andReturn($failed);
        Mail::shouldReceive('to')->with('applicant@gmail.com')->once()->andReturn($successful);
        try {
            (new SendInterviewEmailJob($application, $interview, 'supervisor'))->handle();
            $this->fail('Transport failure must propagate for the queue to retry');
        } catch (RuntimeException $error) {
            $this->assertSame('SMTP rejected recipient', $error->getMessage());
        }
        (new SendInterviewEmailJob($application, $interview, 'applicant'))->handle();
    }

    public function test_both_messages_include_all_uc7_appointment_details(): void
    {
        [$application, $interview] = $this->appointment();
        $html = (new InterviewScheduledMail($application, $interview))->render();
        foreach (['Applicant Name', 'Engineering', '2026-12-01', '10:30', 'HQ room 2'] as $detail) {
            $this->assertStringContainsString($detail, $html);
        }
    }

    public function test_legacy_queued_jobs_fan_out_to_separate_recipient_jobs(): void
    {
        Queue::fake();
        [$application, $interview] = $this->appointment();
        (new SendInterviewEmailJob($application, $interview))->handle();
        Queue::assertPushed(SendInterviewEmailJob::class, 2);
        foreach (['applicant', 'supervisor'] as $recipient) {
            Queue::assertPushed(SendInterviewEmailJob::class, fn ($job) => $job->recipient === $recipient);
        }
    }

    public function test_missing_supervisor_address_is_a_visible_retryable_failure(): void
    {
        Mail::fake();
        [$application, $interview] = $this->appointment();
        $interview->trainer->user->email = '';
        $this->expectException(\InvalidArgumentException::class);
        (new SendInterviewEmailJob($application, $interview, 'supervisor'))->handle();
    }

    public function test_diagnostic_rejects_non_delivering_mailer_and_placeholder_sender(): void
    {
        config(['mail.default' => 'log', 'mail.from.address' => 'hello@example.com']);
        $this->artisan('interviews:mail-check')->expectsOutputToContain('does not deliver email')->assertFailed();
    }

    public function test_diagnostic_requires_worker_for_database_queue_without_exposing_password(): void
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => 'sender@example.org', 'mail.mailers.smtp.host' => 'smtp.example.org', 'mail.mailers.smtp.password' => 'do-not-print', 'queue.default' => 'database']);
        $this->artisan('interviews:mail-check')->expectsOutputToContain('persistent queue worker')->assertSuccessful();
    }
}
