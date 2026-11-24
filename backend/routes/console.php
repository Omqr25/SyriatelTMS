<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
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
//Schedule::command('test:scheduler')
//    ->everyMinute()
//    ->withoutOverlapping();
