<?php

declare(strict_types=1);

namespace CoreX\Access\Tests;

use CoreX\Access\Tests\Support\CoreAuthTestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * PG16 lane for `aut_*` DDL (jsonb, CHECK, UNIQUE NULLS NOT DISTINCT,
 * COMMENT ON — no sqlite equivalent, D105). Schema is reset and assembled
 * per test via explicit `--path` migrate calls (same golden-template
 * composition as packages/tenancy/tests/Feature/WorkspaceResolverTest.php)
 * rather than RefreshDatabase: `--path` migrate runs ONLY the given path,
 * so AzGuard's own loadMigrationsFrom()-registered migrations need their
 * own explicit call too — {@see migrateAzGuard()}.
 */
abstract class PgTestCase extends CoreAuthTestCase
{
    /** @param Application $app */
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('PGSQL_PORT') ?: '5434',
            'database' => getenv('PGSQL_DATABASE') ?: 'corex_test',
            'username' => getenv('PGSQL_USERNAME') ?: 'corex',
            'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ]);
    }

    protected function resetPgSchema(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
        DB::statement('CREATE SCHEMA public');
        DB::purge('pgsql');
    }

    /** AzGuard's own tables (az_guard_roles, az_guard_role_permissions, …) — the compiler's write target. */
    protected function migrateAzGuard(): void
    {
        $this->migratePath(__DIR__.'/../vendor/axioma-studio/azguard-core/database/migrations');
    }

    /** The 3 tenant-DB aut_* migrations under test — no cross-package FK. */
    protected function migrateAutSchema(): void
    {
        $this->migratePath(__DIR__.'/../database/migrations/tenant');
    }

    /** Cloud-only cross-package FK (D102) — requires the users/wsp_workspaces fixtures below to already exist. */
    protected function migrateCloudFks(): void
    {
        $this->migratePath(__DIR__.'/../database/migrations/tenant-cloud');
    }

    /** Minimal users/wsp_workspaces fixtures (D105) — real axiomasoft/corex-tenancy is not a dependency (boxed doctrine). */
    protected function migrateFixtures(): void
    {
        $this->migratePath(__DIR__.'/database/migrations/pg');
    }

    /**
     * The REAL `sys_departments` migration from axiomasoft/corex (not a fixture) —
     * closes the P2.20 debt of dept/dept_tree never running against a real
     * ltree-backed table (P2.9). Requires {@see migrateFixtures()} to have
     * already created `users` (department_id's FK target column, though the
     * FK itself is cross-package and lives elsewhere) and `wsp_workspaces`
     * (sys_departments.workspace_id's loose reference).
     */
    protected function migrateCoreDepartments(): void
    {
        $this->migratePath(__DIR__.'/../vendor/axiomasoft/corex/database/migrations/pg');
    }

    private function migratePath(string $path): void
    {
        $this->artisan('migrate', [
            '--database' => 'pgsql',
            '--path' => realpath($path),
            '--realpath' => true,
            '--force' => true,
        ])->run();
    }
}
