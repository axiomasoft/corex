<?php

declare(strict_types=1);

namespace Flex\Crm\Tests\Support;

use CoreX\CoreServiceProvider;
use CoreX\Intl\IntlServiceProvider;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Internal\Compiler\RegistryCompiler;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Operations\ApiServiceProvider;
use Flex\Crm\CrmModule;
use Flex\Crm\CrmServiceProvider;
use Illuminate\Foundation\Application;
use Mockery;
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
            IntlServiceProvider::class,
            CrmServiceProvider::class,
        ];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        // Supply the deploy-time compiled artifact before the runtime morph-map projection.
        $app->booted(static function (Application $app): void {
            $compiled = $app->make(RegistryCompiler::class)->compile(manifests: [(new CrmModule)->manifest()], installedPackages: []);
            $registry = Mockery::mock(ModuleRegistry::class);
            $registry->shouldReceive('compiled')->andReturn($compiled);
            $registry->shouldReceive('isActive')->andReturn(true);
            $app->instance(ModuleRegistry::class, $registry);
        });

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
