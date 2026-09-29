<?php

declare(strict_types=1);

namespace CoreX\Tasks\Tests;

use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;
use CoreX\Tasks\Access\Contracts\TaskPlanningAccess;
use CoreX\Tasks\Tests\Support\TasksTestCase;
use Mockery;
use Override;

abstract class TestCase extends TasksTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $modules = Mockery::mock(ModuleRegistry::class);
        $modules->shouldReceive('isActive')->byDefault()->andReturnTrue();
        $this->app->instance(ModuleRegistry::class, $modules);

        $access = Mockery::mock(TaskAccess::class);
        $access->shouldReceive('authorizeCreate')->byDefault()->andReturnNull();
        $access->shouldReceive('authorizeMove')->byDefault()->andReturnNull();
        $access->shouldReceive('authorizeTarget')->byDefault()->andReturnNull();
        $this->app->instance(TaskAccess::class, $access);

        $mutationAccess = Mockery::mock(TaskMutationAccess::class);
        $mutationAccess->shouldReceive('authorizeUpdate')->byDefault()->andReturnNull();
        $mutationAccess->shouldReceive('authorizeComplete')->byDefault()->andReturnNull();
        $this->app->instance(TaskMutationAccess::class, $mutationAccess);

        $planningAccess = Mockery::mock(TaskPlanningAccess::class);
        $planningAccess->shouldReceive('authorize')->byDefault()->andReturnNull();
        $this->app->instance(TaskPlanningAccess::class, $planningAccess);
    }
}
