<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Tests;

use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\CoreServiceProvider;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Support\Data\AllowAllDataScopeAuthorizer;
use CoreX\Tasks\Contracts\TaskAccess;
use CoreX\Tasks\Contracts\TaskMutationAccess;
use CoreX\Tasks\TasksServiceProvider;
use CoreX\TasksWorkflow\Contracts\TaskRecurrenceCommand;
use CoreX\TasksWorkflow\TasksWorkflowServiceProvider;
use CoreX\TasksWorkflow\Tests\Support\IsolatesDatabasePath;
use Illuminate\Foundation\Application;
use Mockery;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;

abstract class TestCase extends Orchestra
{
    use IsolatesDatabasePath;

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
        $this->app->instance(DataScopeAuthorizer::class, new AllowAllDataScopeAuthorizer);

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
            TasksWorkflowServiceProvider::class,
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
        $this->loadMigrationsFrom(__DIR__.'/../../tasks/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../tasks/database/migrations/2026_09_15_000071_create_task_operation_receipts.php');
    }
}
