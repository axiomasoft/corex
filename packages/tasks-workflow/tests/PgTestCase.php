<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Override;

abstract class PgTestCase extends TestCase
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
        return $this->migratePath(__DIR__.'/../../tasks/tests/database/migrations');
    }

    protected function migrateTasks(): int
    {
        return $this->migratePath(__DIR__.'/../../tasks/database/migrations');
    }

    protected function migrateTasksWorkflow(): int
    {
        return $this->migrateFile(__DIR__.'/../database/migrations/2026_09_15_000072_create_task_series_mapping.php');
    }

    protected function migrateWakeup(): int
    {
        return $this->migratePath(__DIR__.'/../../wakeup/database/migrations');
    }

    protected function migrateWorkflow(): int
    {
        return $this->migratePath(__DIR__.'/../../workflow/database/migrations');
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
