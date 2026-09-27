<?php

declare(strict_types=1);

namespace CoreX\Tasks\Tests;

use CoreX\Tasks\Tests\Support\TasksTestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Override;

abstract class PgTestCase extends TasksTestCase
{
    /** @param Application $app */
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

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

    protected function migrateFixtures(): int
    {
        return $this->migratePath(__DIR__.'/database/migrations');
    }

    protected function migrateTasks(): int
    {
        return $this->migratePath(__DIR__.'/../database/migrations');
    }

    protected function migrateFile(string $file): int
    {
        return $this->migratePath($file);
    }

    private function migratePath(string $path): int
    {
        return $this->artisan('migrate', [
            '--database' => 'pgsql',
            '--path' => realpath($path),
            '--realpath' => true,
            '--force' => true,
        ])->run();
    }
}
