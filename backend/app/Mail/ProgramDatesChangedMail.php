<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProgramDatesChangedMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $applicantName, public array $schedule)
    {
        $this->beforeCommit();
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Training program date email failed.', [
            'training_program_id' => $this->schedule['id'],
            'exception_type' => $exception::class,
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Updated dates for '.$this->schedule['name']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.program-dates-changed');
    }
}
