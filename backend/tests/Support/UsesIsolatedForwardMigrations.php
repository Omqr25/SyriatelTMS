<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait UsesIsolatedForwardMigrations
{
    use RefreshDatabase;

    protected function migrateDatabases(): void
    {
        IsolatedTestDatabase::verify($this->app);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }
}
