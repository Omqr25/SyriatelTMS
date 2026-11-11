<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

#[Signature('app:test-scheduler')]
#[Description('Command description')]
class TestScheduler extends Command
{
    protected $signature = 'test:scheduler';

    protected $description = 'Test that Laravel Scheduler is running';
    /**
     * Execute the console command.
     */


    public function handle(): int
    {
        $message = 'Scheduler executed at ' . now()->toDateTimeString();

        Log::info($message);

        Storage::disk('local')->append(
            'health-checks/scheduler.log',
            $message
        );

        $this->info($message);

        return self::SUCCESS;
    }
}
