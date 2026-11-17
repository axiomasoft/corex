<?php

declare(strict_types=1);

namespace CoreX\Access\Tests\Support;

use CoreX\Access\AccessServiceProvider;
use CoreX\CoreServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\ModulesServiceProvider;
use Packman\Modules\Testing\ArrayEntityRegistry;

/**
 * Testbench base wiring axiomasoft/corex and axiomasoft/corex-access.
 */
abstract class CoreAuthTestCase extends Orchestra
{
    use IsolatesDatabasePath;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CoreServiceProvider::class,
            ModulesServiceProvider::class,
            AccessServiceProvider::class,
        ];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        // Tests seed the public fixture explicitly; production uses the runtime registry.
        $app->singleton(ArrayEntityRegistry::class, static fn (Application $app): ArrayEntityRegistry => new ArrayEntityRegistry(
            moduleActive: static fn (string $module, mixed $ctx): bool => $app->make(ModuleRegistry::class)->isActive($module, $ctx),
        ));
        $app->singleton(EntityRegistry::class, static fn (Application $app): ArrayEntityRegistry => $app->make(ArrayEntityRegistry::class));

        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
