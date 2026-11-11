<?php

namespace App\Jobs;

use App\Mail\AccountCreatedMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendAccountCreatedEmailJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */

    public function __construct(public User $user, public string $password, public string $role,) {}


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to($this->user->email)->send(
            new AccountCreatedMail(
                name: $this->user->name,
                email: $this->user->email,
                password: $this->password,
                role: $this->role,
            )
        );
    }
}
