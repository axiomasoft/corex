<?php

declare(strict_types=1);

namespace CoreX\Crm\Testing;

use CoreX\Contracts\TenantService;
use CoreX\CoreServiceProvider;
use CoreX\Crm\CrmServiceProvider;
use CoreX\Tenancy\ContainerTenantService;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class CrmTestCase extends Orchestra
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
            CoreServiceProvider::class,
            CrmServiceProvider::class,
        ];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('corex.tenancy.service', ContainerTenantService::class);
    }
}
