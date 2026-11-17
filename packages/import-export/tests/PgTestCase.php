<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Tests;

use CoreX\CoreServiceProvider;
use CoreX\ImportExport\ImportExportServiceProvider;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use RuntimeException;

abstract class PgTestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, ImportExportServiceProvider::class];
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
        DB::statement('CREATE SCHEMA public');
        DB::purge('pgsql');

        resolve(SchemaMigrator::class)->migrateSets(['axiomasoft/corex-import-export:tenant'], DB::getDefaultConnection());
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        $database = getenv('PGSQL_DATABASE') ?: 'corex_test';

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException('Transfer PostgreSQL tests require PGSQL_DATABASE ending in _test.');
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
}
