<?php

namespace App\Mail;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationDecisionMail extends Mailable
{
    use Queueable, SerializesModels;
    /**
     * Create a new message instance.
     */
    public bool $accepted;

    public function __construct(
        public Application $application
    ) {
        $this->accepted = $application->status === ApplicationStatus::ACCEPTED;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $accepted = $this->accepted;

        return new Envelope(
            subject: $accepted
                ? 'Your Syriatel Training Application Was Accepted'
                : 'Your Syriatel Training Application Decision',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.application-decision',
            with: [
                'application' => $this->application,
                'accepted' => $this->accepted,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }


    public function build(): static
    {
        $accepted = $this->accepted;

        return $this
            ->subject(
                $accepted
                    ? 'Your Syriatel Training Application Was Accepted'
                    : 'Your Syriatel Training Application Decision'
            )
            ->view('emails.application-decision')
            ->with([
                'application' => $this->application,
                'accepted' => $accepted,
            ]);
    }
}
