<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests;

use CoreX\CoreServiceProvider;
use CoreX\Operations\OperationsServiceProvider;
use CoreX\Operations\Tests\Fixtures\TrustedClient;
use CoreX\Webhooks\WebhooksServiceProvider;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use Packman\Modules\ModulesServiceProvider;
use Packman\Schema\Contracts\Migration\SchemaSets;
use Packman\Schema\Testing\MigratesSchemaSets;

abstract class TestCase extends Orchestra
{
    use MigratesSchemaSets;

    protected function rollbackSchemaSet(string $id, ?string $connection = null): void
    {
        $set = $this->app->make(SchemaSets::class)->get($id);
        $migrator = $this->app->make('migrator');
        assert($migrator instanceof Migrator);
        $migrator->usingConnection($set->connection ?? $connection, fn () => $migrator->rollback([$set->path], ['step' => count(glob($set->path.'/*.php') ?: [])]));
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @param Application $app */
    protected function getPackageProviders($app): array
    {
        return [OperationsServiceProvider::class, WebhooksServiceProvider::class, CoreServiceProvider::class, ModulesServiceProvider::class];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('corex-operations.routes.middleware', [TrustedClient::class]);
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.api_schema_budget', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('corex-operations.schema', [
            'budget' => ['connection' => 'api_schema_budget', 'driver' => 'sqlite', 'database' => ':memory:'],
            'tenant' => ['connection' => 'sqlite', 'driver' => 'sqlite', 'database' => ':memory:'],
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
    }
}
