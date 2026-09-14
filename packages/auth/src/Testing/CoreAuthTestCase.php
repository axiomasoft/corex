<?php

declare(strict_types=1);

namespace CoreX\Auth\Testing;

use AzGuard\AzGuardServiceProvider;
use CoreX\Auth\CoreAuthServiceProvider;
use CoreX\CoreServiceProvider;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Testing\IsolatesDatabasePath;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Testbench base wiring corex/core + AzGuard + corex/auth.
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
    }
}
