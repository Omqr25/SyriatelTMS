<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\Support\IsolatedTestDatabase;
use Tests\TestCase;

class IsolatedTestDatabaseTest extends TestCase
{
    public function test_only_in_memory_or_explicitly_authorized_local_test_connections_are_allowed(): void
    {
        $mysql = ['driver' => 'mysql', 'database' => 'isolated_test', 'host' => '127.0.0.1', 'username' => 'test_account'];
        $this->assertTrue(IsolatedTestDatabase::allows($mysql, 'isolated_test'));
        $this->assertFalse(IsolatedTestDatabase::allows($mysql, false));
        $this->assertFalse(IsolatedTestDatabase::allows($mysql, 'other_test'));

        foreach ([
            ['host' => 'staging.local'], ['database' => 'development'], ['username' => 'root'],
            ['url' => 'mysql://staging.local/isolated_test'], ['unix_socket' => '/tmp/mysql.sock'],
            ['write' => ['host' => 'staging.local']],
        ] as $override) {
            $this->assertFalse(IsolatedTestDatabase::allows(array_replace($mysql, $override), 'isolated_test'));
        }
        $this->assertFalse(IsolatedTestDatabase::allows(['driver' => 'sqlite', 'database' => '/tmp/development.sqlite'], false));
        $this->assertTrue(IsolatedTestDatabase::allows(['driver' => 'sqlite', 'database' => ':memory:'], false));
    }

    public function test_destructive_commands_recheck_the_named_connection_before_execution(): void
    {
        config(['database.connections.unverified' => ['driver' => 'sqlite', 'database' => '/unverified.sqlite']]);
        foreach (['migrate:fresh', 'migrate:rollback', 'migrate:reset', 'migrate:refresh', 'db:wipe'] as $command) {
            try {
                Artisan::call($command, ['--database' => 'unverified']);
                $this->fail("{$command} should reject an unverified database.");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('explicitly authorized', $exception->getMessage());
            }
        }
    }
}
