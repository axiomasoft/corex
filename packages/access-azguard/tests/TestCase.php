<?php

declare(strict_types=1);

namespace CoreX\AccessAzguard\Tests;

use AzGuard\AzGuardServiceProvider;
use CoreX\Access\CoreAuthServiceProvider;
use CoreX\AccessAzguard\AccessAzguardServiceProvider;
use CoreX\CoreServiceProvider;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Modules\Testing\ArrayEntityRegistry;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--force' => true])->run();
    }

    /** @param Application $app */
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, ModulesServiceProvider::class, AzGuardServiceProvider::class,
            CoreAuthServiceProvider::class, AccessAzguardServiceProvider::class];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app->singleton(ArrayEntityRegistry::class, static fn (Application $app): ArrayEntityRegistry => new ArrayEntityRegistry(
            moduleActive: static fn (string $module, mixed $ctx): bool => $app->make(ModuleRegistry::class)->isActive($module, $ctx),
        ));
        $app->singleton(EntityRegistry::class, static fn (Application $app): ArrayEntityRegistry => $app->make(ArrayEntityRegistry::class));

        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
