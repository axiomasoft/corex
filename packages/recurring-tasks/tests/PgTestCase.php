<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Tests;

use CoreX\Scheduler\Internal\SchedulerSchemaSets;
use CoreX\Workflow\Internal\WorkflowSchemaSets;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Override;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;
use Packman\Schema\Testing\MigratesSchemaSets;

abstract class PgTestCase extends TestCase
{
    use MigratesSchemaSets;

    /** @param Application $app */
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app->tag([SchedulerSchemaSets::class, WorkflowSchemaSets::class], SchemaSetProvider::class);

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

    protected function migrateRecurringTasks(): int
    {
        $this->migrateSchemaSets(['axiomasoft/corex-recurring-tasks:tenant'], 'pgsql');

        return 0;
    }

    protected function migrateWakeup(): int
    {
        $this->migrateSchemaSets(['axiomasoft/corex-scheduler:tenant'], 'pgsql');

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
