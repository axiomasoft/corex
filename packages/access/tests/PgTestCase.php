<?php

declare(strict_types=1);

namespace CoreX\Access\Tests;

use CoreX\Access\Tests\Support\CoreAuthTestCase;
use CoreX\Access\Tests\Support\FixtureTenantSchemaSets;
use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Override;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;
use Packman\Schema\Testing\MigratesSchemaSets;

/**
 * PG16 lane for `aut_*` DDL (jsonb, CHECK, UNIQUE NULLS NOT DISTINCT,
 * COMMENT ON — no sqlite equivalent). Schema is reset and assembled
 * per test via explicit `--path` migrate calls (same golden-template
 * composition as packages/tenancy/tests/Feature/WorkspaceResolverTest.php)
 * rather than RefreshDatabase: `--path` migrate runs ONLY the given path.
 */
abstract class PgTestCase extends CoreAuthTestCase
{
    use MigratesSchemaSets;

    /** @param Application $app */
    #[Override]
    protected function defineEnvironment($app): void
    {
        $app->tag([FixtureTenantSchemaSets::class], SchemaSetProvider::class);
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
        $connection = DB::connection('pgsql');
        $context = resolve(TenantContextResolver::class)->current();
        $host = WorkScopeFixture::install($this->app, principalId: 'access-fixture-operator');
        $binding = $host->register($context, $connection);
        // Reset can reconnect the same test account twice. Attest the newly
        // selected connection, keeping the independent route and ticket history.
        $this->app->instance(PhysicalBindingVerifier::class, new readonly class($connection, $binding, $context) implements PhysicalBindingVerifier
        {
            public function __construct(private ConnectionInterface $owner, private PhysicalBinding $expected, private TenantContext $context) {}

            public function verify(PhysicalBinding $binding, TenantContext $context, ConnectionInterface $connection): void
            {
                if ($connection !== $this->owner || $binding->bindingId !== $this->expected->bindingId || $binding->restoreEpoch !== $this->expected->restoreEpoch
                    || $binding->bindingGeneration !== $this->expected->bindingGeneration || $context->account->id !== $this->context->account->id) {
                    throw new ScopeContextMismatch('Unexpected Access fixture owner.');
                }
            }
        });
    }

    /** Tenant-DB aut_* migrations under test — no cross-package FK. */
    protected function migrateAutSchema(): void
    {
        $this->migrateSchemaSets(['axiomasoft/corex-access:tenant'], 'pgsql');
    }

    /** Cloud-only cross-package FK — requires the users/wsp_workspaces fixtures below to already exist. */
    protected function migrateCloudFks(): void
    {
        $this->migrateSchemaSets(['axiomasoft/corex-access:tenant-cloud'], 'pgsql');
    }

    /** Minimal users/wsp_workspaces fixtures — real axiomasoft/corex-tenancy is not a dependency (boxed doctrine). */
    protected function migrateFixtures(): void
    {
        $this->migratePath(__DIR__.'/database/migrations/pg');
    }

    /**
     * The REAL `sys_departments` migration from axiomasoft/corex (not a fixture) —
     * closes the debt of dept/dept_tree never running against a real
     * ltree-backed table. Requires {@see migrateFixtures()} to have
     * already created `users` (department_id's FK target column, though the
     * FK itself is cross-package and lives elsewhere) and `wsp_workspaces`
     * (sys_departments.workspace_id's loose reference).
     */
    protected function migrateCoreDepartments(): void
    {
        $this->migrateSchemaSets(['axiomasoft/corex:tenant'], 'pgsql');
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
