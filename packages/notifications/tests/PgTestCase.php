<?php

declare(strict_types=1);

namespace CoreX\Notifications\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Override;
use Packman\Modules\ModulesServiceProvider;
use RuntimeException;

abstract class PgTestCase extends TestCase
{
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ModulesServiceProvider::class];
    }

    /** @param Application $app */
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $database = getenv('PGSQL_DATABASE') ?: 'corex_test';

        if (preg_match('/^[a-z0-9_]+_test$/D', $database) !== 1) {
            throw new RuntimeException('Notifications PostgreSQL tests require PGSQL_DATABASE ending in _test.');
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
        DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
        DB::statement('CREATE SCHEMA public');

        parent::defineDatabaseMigrations();
    }
}
