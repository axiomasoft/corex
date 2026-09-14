<?php

declare(strict_types=1);

namespace Flex\Crm\Tests;

use Flex\Crm\Testing\CrmTestCase;
use RuntimeException;

abstract class TestCase extends CrmTestCase
{
    protected function defineDatabaseMigrations(): void
    {
        if (! static::usesRefreshDatabaseTestingConcern()) {
            return; // PG tests explicitly own the isolated database lifecycle.
        }
        $this->loadMigrationsFrom(__DIR__.'/database');
        parent::defineDatabaseMigrations();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('database.connections.testbench.host', '');
        $app['config']->set('database.connections.testbench.port', 0);
        $app['config']->set('database.connections.testbench.username', '');
        $database = $app['config']->get('database.connections.pgsql.database');

        if (! is_string($database) || ! preg_match('/^[a-z0-9_]+_test$/D', $database)) {
            throw new RuntimeException('CRM tests require an isolated PGSQL_DATABASE ending in _test before any database mutation.');
        }
    }
}
