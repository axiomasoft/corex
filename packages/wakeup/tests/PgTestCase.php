<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Override;
use RuntimeException;

abstract class PgTestCase extends TestCase
{
    /** @param Application $app */
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $database = getenv('PGSQL_DATABASE') ?: 'corex_test';

        if (preg_match('/^[a-z0-9_]+_test$/D', $database) !== 1) {
            throw new RuntimeException('Wakeup PostgreSQL tests require PGSQL_DATABASE ending in _test.');
        }

        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('PGSQL_PORT') ?: '5434',
            'database' => $database,
            'username' => getenv('PGSQL_USERNAME') ?: 'corex',
            'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ]);
    }

    #[Override]
    protected function defineDatabaseMigrations(): void
    {
        Schema::dropIfExists('wakeup_effects');
        Schema::dropIfExists('sys_wakeup_requests');

        parent::defineDatabaseMigrations();
    }
}
