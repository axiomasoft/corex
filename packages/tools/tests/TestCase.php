<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests;

use CoreX\CoreServiceProvider;
use CoreX\Operations\OperationsServiceProvider;
use CoreX\Tools\ToolsServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use Packman\Modules\ModulesServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @param Application $app */
    protected function getPackageProviders($app): array
    {
        return [OperationsServiceProvider::class, ToolsServiceProvider::class, CoreServiceProvider::class, ModulesServiceProvider::class];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
    }
}
