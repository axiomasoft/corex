<?php

declare(strict_types=1);

namespace CoreX\Workflow\Tests;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Time\Laravel\TimeRegistrar;
use CoreX\Workflow\Contracts\EngineAdapter;
use CoreX\Workflow\Data\SignalRequest;
use CoreX\Workflow\Data\SignalResult;
use CoreX\Workflow\Data\StartResult;
use CoreX\Workflow\Data\TransitionRequest;
use CoreX\Workflow\Data\TransitionResult;
use CoreX\Workflow\Data\TriggerRequest;
use CoreX\Workflow\WorkflowServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;

final class TestTenantContextResolver implements TenantContextResolver
{
    public function current(): TenantContext
    {
        return new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'workflow-test', 'active', [], []));
    }
}

final class FakeEngineAdapter implements EngineAdapter
{
    public function start(TriggerRequest $request): StartResult
    {
        return new StartResult(status: 'started', instanceRef: 'instance:1', errorCode: null);
    }

    public function signal(SignalRequest $request): SignalResult
    {
        return new SignalResult(status: 'buffered');
    }

    public function fire(TransitionRequest $request): TransitionResult
    {
        return new TransitionResult(status: 'completed');
    }
}

abstract class TestCase extends Orchestra
{
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [WorkflowServiceProvider::class];
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('corex-workflow.enforce_declarations', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        (new TimeRegistrar)->register($this->app);
        $this->app->instance(TenantContextResolver::class, new TestTenantContextResolver);
        $this->app->instance(EngineAdapter::class, new FakeEngineAdapter);
    }
}
