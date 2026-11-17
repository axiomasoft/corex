<?php

declare(strict_types=1);

namespace CoreX\Intl\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Override;
use Packman\Schema\Testing\MigratesSchemaSets;
use RuntimeException;

abstract class PgTestCase extends TestCase
{
    use MigratesSchemaSets;

    /** @param Application $app */
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $database = getenv('PGSQL_DATABASE') ?: 'corex_test';

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException('Intl PostgreSQL tests require PGSQL_DATABASE ending in _test.');
        }

        $app['config']->set('database.default', 'pgsql');
    }

    #[Override]
    protected function defineDatabaseMigrations(): void {}

    protected function resetPgSchema(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
        DB::statement('CREATE SCHEMA public');
        DB::purge('pgsql');
    }

    protected function migrateIntl(): int
    {
        $this->migrateSchemaSets(['axiomasoft/corex-intl:tenant'], 'pgsql');

        return 0;
    }
}
