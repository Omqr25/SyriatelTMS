<?php

use App\Jobs\TestQueueJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
if (app()->environment('local')) {
    Artisan::command('tms:test-email {recipient}', function (string $recipient) {
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid recipient email address.');

            return \Symfony\Component\Console\Command\Command::INVALID;
        }

        Mail::raw('This is a test email from Syriatel TMS.', function ($message) use ($recipient) {
            $message->to($recipient)->subject('Syriatel TMS Email Test');
        });
        $this->info('Test email submitted to the configured mail transport.');
    })->purpose('Test the configured mail transport from a local environment');

    Artisan::command('tms:test-queue', function () {
        TestQueueJob::dispatch();
        $this->info('Test job dispatched to the configured queue.');
    })->purpose('Dispatch a test job from a local environment');
}

Artisan::command('notifications:deliver {--limit=25}', function (\App\Services\Notification\InboxPushDelivery $delivery) {
    $remaining = $delivery->recover(max(1, min(100, (int) $this->option('limit'))));
    $this->info("Due notification deliveries remaining: $remaining");
})->purpose('Deliver pending inbox pushes and recover missed queue dispatches');

Artisan::command('applicant-exports:reconcile', function (\App\Services\Training\ApplicantExport\ExportQueue $queue) {
    $queue->reconcile();
})->purpose('Reconcile interrupted applicant exports and enqueue bounded work');
Schedule::command('applicant-exports:reconcile')->everyMinute()->withoutOverlapping(5);

Schedule::command('model:prune', ['--model' => [\App\Models\EnglishScoreImport::class]])->daily();
Schedule::command('notifications:deliver')->everyMinute()->withoutOverlapping(10)->runInBackground();
Schedule::command('training-programs:remind-hr')->hourly()->withoutOverlapping();
Schedule::command('training-programs:update-status')
    ->everyMinute();
Schedule::command('app:update-overdue-tasks')
    ->everyMinute();


Artisan::command('certificates:clean-packages', function (\App\Services\Certificate\CertificatePackages $packages) {
    $this->info($packages->cleanup().' expired certificate packages removed.');
})->purpose('Remove expired private certificate packages');
Schedule::command('certificates:clean-packages')->hourly()->withoutOverlapping(10);
