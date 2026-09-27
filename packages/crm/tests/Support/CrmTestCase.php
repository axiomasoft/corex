<?php

declare(strict_types=1);

namespace Flex\Crm\Tests\Support;

use CoreX\Api\ApiServiceProvider;
use CoreX\CoreServiceProvider;
use CoreX\Modules\ModulesServiceProvider;
use Flex\Crm\CrmServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Package-local Testbench base for CRM suites (D94).
 */
abstract class CrmTestCase extends Orchestra
{
    use IsolatesDatabasePath;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CoreServiceProvider::class,
            ModulesServiceProvider::class,
            ApiServiceProvider::class,
            CrmServiceProvider::class,
        ];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('PGSQL_PORT') ?: '5434',
            'database' => getenv('PGSQL_DATABASE') ?: 'corex_test',
            'username' => getenv('PGSQL_USERNAME') ?: 'corex',
            'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ]);
        $app['config']->set('corex.audit.enabled', false);
    }
}
