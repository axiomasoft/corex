<?php

declare(strict_types=1);

namespace CoreX\Auth\Testing;

use AzGuard\AzGuardServiceProvider;
use CoreX\Auth\CoreAuthServiceProvider;
use CoreX\Contracts\TenantService;
use CoreX\CoreServiceProvider;
use CoreX\Tenancy\ContainerTenantService;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Testbench base wiring corex/core + AzGuard + corex/auth with multi-tenancy
 * active (ContainerTenantService bound).
 */
abstract class CoreAuthTestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Bind tenancy after boot so it is active regardless of Testbench's
        // config-timing (a real app reads corex.tenancy.service at register).
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
            AzGuardServiceProvider::class,
            CoreAuthServiceProvider::class,
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

        // Activate tenancy so the TenantScope / boundary are live in tests.
        $app['config']->set('corex.tenancy.service', ContainerTenantService::class);
    }
}
