<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Schedule::command('training-programs:update-status')
    ->everyMinute();
//Schedule::command('app:update-overdue-tasks')
//    ->everyMinute();
//Schedule::command('test:scheduler')
//    ->everyMinute()
//    ->withoutOverlapping();
