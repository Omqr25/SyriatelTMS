<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

final class IsolatedTestDatabase
{
    public static function verify(Application $app, ?string $name = null): void
    {
        $db = $app['db'];
        $name ??= $db->getDefaultConnection();
        $config = $app['config']->get("database.connections.{$name}", []);

        if (! $app->environment('testing') || ! self::allows($config, getenv('TMS_ISOLATED_MYSQL_DATABASE'))) {
            throw new RuntimeException('Tests require in-memory SQLite or an explicitly authorized, disposable local MySQL test database.');
        }

        if ($config['driver'] === 'mysql') {
            $expected = getenv('TMS_ISOLATED_MYSQL_SERVER_UUID');
            if (! $expected) {
                throw new RuntimeException('Set TMS_ISOLATED_MYSQL_SERVER_UUID from the disposable MySQL instance.');
            }

            $identity = $db->connection($name)->getPdo()
                ->query('SELECT DATABASE() AS db_name, @@server_uuid AS server_uuid')
                ->fetch();
            if ($identity['db_name'] !== $config['database'] || $identity['server_uuid'] !== $expected) {
                throw new RuntimeException('The connected MySQL server or database does not match the explicitly authorized test target.');
            }
        }
    }

    public static function allows(array $config, string|false $authorizedDatabase): bool
    {
        if (! empty($config['url']) || isset($config['read']) || isset($config['write'])) {
            return false;
        }

        $database = $config['database'] ?? null;
        if (($config['driver'] ?? null) === 'sqlite') {
            return $database === ':memory:';
        }

        return ($config['driver'] ?? null) === 'mysql'
            && $authorizedDatabase !== false
            && $database === $authorizedDatabase
            && preg_match('/\A[a-z0-9_]+_test\z/', $authorizedDatabase) === 1
            && ($config['host'] ?? null) === '127.0.0.1'
            && ! empty($config['username'])
            && $config['username'] !== 'root'
            && empty($config['unix_socket'])
            && empty($config['options']);
    }
}
