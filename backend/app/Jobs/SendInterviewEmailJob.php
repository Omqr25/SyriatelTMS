<?php

namespace App\Jobs;

use App\Mail\InterviewScheduledMail;
use App\Models\Application;
use App\Models\Interview;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

class SendInterviewEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    // A null recipient supports jobs queued before per-recipient delivery existed.
    public ?string $recipient = null;

    public function __construct(
        public Application $application,
        public Interview $interview,
        ?string $recipient = null,
    ) {
        $this->recipient = $recipient;
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        if ($this->recipient === null) {
            foreach (['applicant', 'supervisor'] as $recipient) {
                self::dispatch($this->application, $this->interview, $recipient);
            }

            return;
        }

        $this->interview->loadMissing(['trainer.user', 'trainer.department']);
        $email = match ($this->recipient) {
            'applicant' => $this->application->email,
            'supervisor' => $this->interview->trainer?->user?->email,
            default => throw new InvalidArgumentException('Unknown interview email recipient.'),
        };
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Interview recipient has no valid email address.');
        }

        Mail::to($email)->send(new InterviewScheduledMail($this->application, $this->interview));
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Interview email delivery failed after retries.', [
            'application_id' => $this->application->id,
            'interview_id' => $this->interview->id,
            'recipient' => $this->recipient,
            'exception_type' => $exception ? $exception::class : null,
        ]);
    }
}
