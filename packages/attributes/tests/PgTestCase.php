<?php

declare(strict_types=1);

namespace CoreX\Attributes\Tests;

use Illuminate\Support\Facades\Schema;
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
            throw new RuntimeException('Attributes PostgreSQL tests require PGSQL_DATABASE ending in _test.');
        }

        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            // Match the UTC timestamps used by the test clock on every server.
            'timezone' => 'UTC',
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

    protected function defineDatabaseMigrations(): void
    {
        Schema::dropIfExists('sys_attribute_schema_operations');
        Schema::dropIfExists('sys_attribute_eav_values');
        Schema::dropIfExists('sys_attribute_eav_records');
        Schema::dropIfExists('sys_attribute_write_tokens');
        Schema::dropIfExists('sys_attribute_documents');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
