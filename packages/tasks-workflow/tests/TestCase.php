<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Tests;

use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Support\Data\AllowAllDataScopeAuthorizer;
use CoreX\Tasks\Contracts\TaskAccess;
use CoreX\Tasks\Contracts\TaskMutationAccess;
use CoreX\Tasks\Testing\TasksTestCase as TasksPackageTestCase;
use CoreX\TasksWorkflow\TasksWorkflowServiceProvider;
use Illuminate\Foundation\Application;
use Mockery;
use Override;

abstract class TestCase extends TasksPackageTestCase
{
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
            ...parent::getPackageProviders($app),
            TasksWorkflowServiceProvider::class,
        ];
    }

    #[Override]
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../tasks/database/migrations/2026_09_15_000071_create_task_operation_receipts.php');
    }
}
