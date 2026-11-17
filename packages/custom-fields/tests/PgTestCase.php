<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Tests;

use CoreX\CoreServiceProvider;
use CoreX\CustomFields\CustomFieldsServiceProvider;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use Packman\Schema\Testing\MigratesSchemaSets;
use RuntimeException;

abstract class PgTestCase extends Orchestra
{
    use MigratesSchemaSets;

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, CustomFieldsServiceProvider::class];
    }

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
        DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
        DB::statement('CREATE SCHEMA public');
        DB::purge('pgsql');
        $this->migrateSchemaSets(['axiomasoft/corex-custom-fields:tenant'], DB::getDefaultConnection());
    }
}
