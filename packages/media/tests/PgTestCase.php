<?php

declare(strict_types=1);

namespace CoreX\Media\Tests;

use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class PgTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $database = getenv('PGSQL_DATABASE') ?: '';

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException('Media PostgreSQL tests require an explicitly isolated *_test database.');
        }
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('PGSQL_PORT') ?: '5434',
            'database' => $database,
            'username' => getenv('PGSQL_USERNAME') ?: 'corex',
            'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
            'charset' => 'utf8', 'prefix' => '', 'schema' => 'public', 'timezone' => 'UTC',
        ]);
        $app['config']->set('vaulter.database.connection', 'pgsql');
    }

    protected function defineDatabaseMigrations(): void
    {
        if (! $this->app->environment('testing') || ! str_ends_with(DB::connection()->getDatabaseName(), '_test')) {
            throw new RuntimeException('Refusing media migrations outside an isolated testing database.');
        }
        DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
        DB::statement('CREATE SCHEMA public');
        parent::defineDatabaseMigrations();
    }
}
