<?php

declare(strict_types=1);

namespace CoreX\Tasks\Testing;

use CoreX\Contracts\TenantService;
use CoreX\Tasks\TasksServiceProvider;
use CoreX\Tenancy\ContainerTenantService;
use CoreX\Testing\CoreXTestCase;
use Flex\Modules\ModulesServiceProvider;
use Illuminate\Foundation\Application;

/**
 * Testbench base for Tasks suites: core's environment plus the Tasks provider
 * and active multi-tenancy.
 */
abstract class TasksTestCase extends CoreXTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Bind tenancy after boot regardless of Testbench config-timing.
        $this->app->singleton(TenantService::class, ContainerTenantService::class);
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            ModulesServiceProvider::class,
            TasksServiceProvider::class,
        ];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('corex.tenancy.service', ContainerTenantService::class);
    }
}
