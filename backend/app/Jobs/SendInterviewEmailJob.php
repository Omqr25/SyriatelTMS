<?php

namespace App\Jobs;

use App\Mail\InterviewScheduledMail;
use App\Models\Application;
use App\Models\Interview;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendInterviewEmailJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Application $application,
        public Interview $interview
    ) {
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {

        $this->interview->load('trainer.user');


        Mail::to($this->interview->trainer?->user?->email)->send(
            new InterviewScheduledMail(
                $this->application,
                $this->interview
            )
        );
        Mail::to($this->application->email)->send(
            new InterviewScheduledMail(
                $this->application,
                $this->interview
            )
        );

    }


}

