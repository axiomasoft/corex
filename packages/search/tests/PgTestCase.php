<?php

declare(strict_types=1);

namespace CoreX\Search\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use RuntimeException;

abstract class PgTestCase extends Orchestra
{
    #[Override]
    protected function defineEnvironment($app): void
    {
        $database = getenv('PGSQL_DATABASE') ?: 'corex_test';

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException('Search PostgreSQL tests require PGSQL_DATABASE ending in _test.');
        }

        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql', 'host' => getenv('PGSQL_HOST') ?: '127.0.0.1', 'port' => getenv('PGSQL_PORT') ?: '5434',
            'database' => $database, 'username' => getenv('PGSQL_USERNAME') ?: 'corex', 'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
            'charset' => 'utf8', 'prefix' => '', 'schema' => 'public',
        ]);
    }
}
