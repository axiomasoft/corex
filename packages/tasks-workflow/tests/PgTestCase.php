<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Tests;

use CoreX\Database\Contracts\SchemaSetProvider;
use CoreX\Database\Testing\MigratesSchemaSets;
use CoreX\Wakeup\Internal\WakeupSchemaSets;
use CoreX\Workflow\Internal\WorkflowSchemaSets;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Override;

abstract class PgTestCase extends TestCase
{
    use MigratesSchemaSets;

    /** @param Application $app */
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app->tag([WakeupSchemaSets::class, WorkflowSchemaSets::class], SchemaSetProvider::class);

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
        $this->migrateSchemaSets(['axiomasoft/corex-tasks:tenant'], 'pgsql');

        return 0;
    }

    protected function migrateTasksWorkflow(): int
    {
        $this->migrateSchemaSets(['axiomasoft/corex-tasks-workflow:tenant'], 'pgsql');

        return 0;
    }

    protected function migrateWakeup(): int
    {
        $this->migrateSchemaSets(['axiomasoft/corex-wakeup:tenant'], 'pgsql');

        return 0;
    }

    protected function migrateWorkflow(): int
    {
        $this->migrateSchemaSets(['axiomasoft/corex-workflow:tenant'], 'pgsql');

        return 0;
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
