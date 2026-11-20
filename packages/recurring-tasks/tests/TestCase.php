<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Tests;

use CoreX\CoreServiceProvider;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Internal\CallableDataScopeAuthorizer;
use CoreX\RecurringTasks\Contracts\TaskRecurrenceCommand;
use CoreX\RecurringTasks\RecurringTasksServiceProvider;
use CoreX\RecurringTasks\Tests\Support\IsolatesDatabasePath;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;
use CoreX\Tasks\TasksServiceProvider;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Illuminate\Foundation\Application;
use Mockery;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\ModulesServiceProvider;
use Packman\Schema\Testing\MigratesSchemaSets;

abstract class TestCase extends Orchestra
{
    use IsolatesDatabasePath;
    use MigratesSchemaSets;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $access = Mockery::mock(TaskAccess::class);
        $access->shouldReceive('authorizeCreate')->byDefault()->andReturnNull();
        $access->shouldReceive('authorizeMove')->byDefault()->andReturnNull();
        $access->shouldReceive('authorizeTarget')->byDefault()->andReturnNull();
        $this->app->instance(TaskAccess::class, $access);

        $modules = Mockery::mock(ModuleRegistry::class);
        $modules->shouldReceive('isActive')->byDefault()->andReturnTrue();
        $this->app->instance(ModuleRegistry::class, $modules);

        $mutationAccess = Mockery::mock(TaskMutationAccess::class);
        $mutationAccess->shouldReceive('authorizeComplete')->byDefault()->andReturnNull();
        $mutationAccess->shouldReceive('authorizeUpdate')->byDefault()->andReturnNull();
        $this->app->instance(TaskMutationAccess::class, $mutationAccess);
        $this->app->scoped(DataScopeAuthorizer::class, static fn (): DataScopeAuthorizer => new CallableDataScopeAuthorizer(static fn (): bool => true));

        CallerWorkScopeFixture::bind($this->app);

        $this->app->forgetInstance(TaskRecurrenceCommand::class);
        $this->app->forgetScopedInstances();
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [
            CoreServiceProvider::class,
            ModulesServiceProvider::class,
            TasksServiceProvider::class,
            RecurringTasksServiceProvider::class,
        ];
    }

    /** @param Application $app */
    #[Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            // Match the UTC timestamps used by the test clock on every server.
            'timezone' => 'UTC',
            'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('PGSQL_PORT') ?: '5434',
            'database' => getenv('PGSQL_DATABASE') ?: 'corex_test',
            'username' => getenv('PGSQL_USERNAME') ?: 'corex',
            'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ]);
        $app['config']->set('corex.audit.enabled', false);
    }

    #[Override]
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../tasks/tests/database/migrations');
    }

    protected function defineDatabaseMigrationsAfterDatabaseRefreshed(): void
    {
        $this->migrateSchemaSets(['axiomasoft/corex-tasks:tenant', 'axiomasoft/corex-recurring-tasks:tenant'], 'testbench');
    }
}
