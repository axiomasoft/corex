<?php

declare(strict_types=1);

namespace CoreX\Api\Tests;

use CoreX\Api\ApiServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** @param Application $app */
    protected function getPackageProviders($app): array
    {
        return [ApiServiceProvider::class];
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
