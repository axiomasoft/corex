<?php

declare(strict_types=1);

namespace CoreX\Transfer\Tests;

use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use RuntimeException;

abstract class PgTestCase extends Orchestra
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['corex_export_parts', 'corex_export_manifests', 'corex_transfer_outbox', 'corex_transfer_refs', 'corex_transfer_rows', 'corex_transfer_jobs'] as $table) {
            Schema::dropIfExists($table);
        }

        $migration = require __DIR__.'/../database/migrations/2026_09_15_000001_create_corex_transfer_tables.php';
        $migration->up();
        $exportMigration = require __DIR__.'/../database/migrations/2026_09_15_000002_create_corex_export_manifest_tables.php';
        $exportMigration->up();
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
