<?php

declare(strict_types=1);

namespace CoreX\Crm\Testing;

use CoreX\Contracts\TenantService;
use CoreX\Crm\CrmServiceProvider;
use CoreX\Tenancy\ContainerTenantService;
use CoreX\Testing\CoreXTestCase;
use Illuminate\Foundation\Application;

/**
 * Testbench base for CRM suites: core's environment plus the CRM provider and
 * active multi-tenancy.
 */
abstract class CrmTestCase extends CoreXTestCase
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
            CrmServiceProvider::class,
        ];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('corex.tenancy.service', ContainerTenantService::class);
    }
}
