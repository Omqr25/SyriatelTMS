<?php

namespace Tests;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\IsolatedTestDatabase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        IsolatedTestDatabase::verify($app);
        // Laravel normally disables console command events during tests.
        $app->make(Kernel::class)->rerouteSymfonyCommandEvents();

        $app['events']->listen(CommandStarting::class, static function (CommandStarting $event) use ($app): void {
            if ($event->command === 'db:wipe' || str_starts_with($event->command ?? '', 'migrate')) {
                $connection = $event->input->hasOption('database') ? $event->input->getOption('database') : null;
                IsolatedTestDatabase::verify($app, $connection);
            }
        });

        return $app;
    }
}
