<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Tests;

use CoreX\CoreServiceProvider;
use CoreX\ImportExport\ImportExportServiceProvider;
use Orchestra\Testbench\TestCase;
use Packman\Schema\Testing\MigratesSchemaSets;

abstract class FactoryTestCase extends TestCase
{
    use MigratesSchemaSets;

    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, ImportExportServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('corex.audit.enabled', false);
        // The full Laravel test host already occupies more than the default worker budget.
        $app['config']->set('corex-import-export.limits.max_memory_mib', 512);
    }
}
