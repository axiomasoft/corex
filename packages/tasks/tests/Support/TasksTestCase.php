<?php

declare(strict_types=1);

namespace CoreX\Tasks\Tests\Support;

use CoreX\Context\Contracts\ControlStoreIsolation;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Context\Laravel\ScopeControlSchema;
use CoreX\Context\Laravel\ScopeOptions;
use CoreX\CoreServiceProvider;
use CoreX\Data\Data\DataScope;
use CoreX\Tasks\TasksServiceProvider;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Orchestra\Testbench\TestCase as Orchestra;
use Packman\Modules\ModulesServiceProvider;
use Packman\Schema\Testing\MigratesSchemaSets;
use RuntimeException;

/**
 * Package-local Testbench base for Tasks suites (D94).
 */
abstract class TasksTestCase extends Orchestra
{
    use IsolatesDatabasePath;
    use MigratesSchemaSets;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CoreServiceProvider::class,
            ModulesServiceProvider::class,
            TasksServiceProvider::class,
        ];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $pgsql = [
            'driver' => 'pgsql',
            'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('PGSQL_PORT') ?: '5434',
            'database' => getenv('PGSQL_DATABASE') ?: 'corex_test',
            'username' => getenv('PGSQL_USERNAME') ?: 'corex',
            'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ];
        $app['config']->set('database.connections.pgsql', $pgsql);

        if (getenv('COREX_TASKS_FEATURE_PG') === '1') {
            $app['config']->set('database.connections.testbench', $pgsql);
        }

        $control = getenv('PGSQL_CONTROL_DATABASE');

        if ($control !== false) {
            if (! str_ends_with($control, '_test') || $control === $pgsql['database']) {
                throw new RuntimeException('Tasks tests require a distinct PostgreSQL control database ending in _test.');
            }

            $app['config']->set('database.connections.tasks-scope-control', array_replace($pgsql, ['database' => $control]));
        } else {
            $app['config']->set('database.connections.tasks-scope-control', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        }

        $app['config']->set('corex.audit.enabled', false);
    }

    protected function establishWorkScope(): DataScope
    {
        $deployment = 'tasks-test-'.Str::uuid7();
        $binding = new PhysicalBinding($deployment, 'tasks-owner', 1, 'tasks-incarnation', str_repeat('a', 64));
        $owner = DB::connection();
        $control = DB::connection('tasks-scope-control');
        $context = resolve(TenantContextResolver::class)->current();

        if (! $control->getSchemaBuilder()->hasTable('corex_scope_routes')) {
            ScopeControlSchema::install($control);
        }

        config()->set('corex.scope.deployment_id', $deployment);
        config()->set('corex.scope.control_connection', 'tasks-scope-control');
        $this->app->forgetInstance(ScopeOptions::class);

        $isolation = Mockery::mock(ControlStoreIsolation::class);
        $isolation->shouldReceive('verify')->with($control, $deployment)->andReturnNull();
        $this->app->instance(ControlStoreIsolation::class, $isolation);

        $verifier = Mockery::mock(PhysicalBindingVerifier::class);
        $verifier->shouldReceive('verify')->andReturnUsing(
            static function (PhysicalBinding $binding, TenantContext $context, ConnectionInterface $connection) use ($owner, $deployment): void {
                if ($connection !== $owner || $binding->deploymentId !== $deployment) {
                    throw new ScopeContextMismatch('The selected connection is not the Tasks test owner.');
                }
            },
        );
        $this->app->instance(PhysicalBindingVerifier::class, $verifier);

        $authority = Mockery::mock(WorkPrincipalAuthority::class);
        $authority->shouldReceive('current')->andReturn(new ScopePrincipal('user', 'tasks-test-operator'));
        $this->app->instance(WorkPrincipalAuthority::class, $authority);

        resolve(DatabaseScopeControl::class)->register($context->account->id, $binding);

        return resolve(DataScopeFactory::class)->forCurrentPrincipal($context);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

    }

    protected function defineDatabaseMigrationsAfterDatabaseRefreshed(): void
    {
        $this->migrateSchemaSets(['axiomasoft/corex-tasks:tenant'], 'testbench');
    }
}
