<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests;

use CoreX\CoreServiceProvider;
use CoreX\Database\Contracts\SchemaSets;
use CoreX\Database\Testing\MigratesSchemaSets;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Operations\ApiServiceProvider;
use CoreX\Webhooks\WebhooksServiceProvider;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

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
        return [ApiServiceProvider::class, WebhooksServiceProvider::class, CoreServiceProvider::class, ModulesServiceProvider::class];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
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
