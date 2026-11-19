<?php

declare(strict_types=1);

namespace CoreX\Access\Tests\Authorization;

use AzGuard\AzGuardServiceProvider;
use CoreX\Access\AccessServiceProvider;
use CoreX\Access\Matrix\Contracts\EntityActivity;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\CoreServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Mockery;
use Orchestra\Testbench\TestCase as Orchestra;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Data\Entities\EntityDefinition;
use Packman\Modules\Testing\ArrayEntityRegistry;
use RuntimeException;

abstract class PolicyTestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [AzGuardServiceProvider::class, BridgePanel::class, AccessServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app->register(CoreServiceProvider::class);
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('azguard.cache.namespace', 'corex-bridge-test');
        $app['config']->set('corex-access.policy_panels', ['test.project.view' => 'app']);
        $registry = new ArrayEntityRegistry;
        $registry->register(new EntityDefinition('test.project', Project::class, 'projects', 'test', 'Project', 'Projects', [], [], null));
        $app->instance(EntityRegistry::class, $registry);
        $activity = Mockery::mock(EntityActivity::class);
        $activity->shouldReceive('isActive')->with('test.project')->andReturnTrue();
        $activity->shouldReceive('isActive')->with('inactive')->andReturnFalse();
        $app->instance(EntityActivity::class, $activity);
        $tenancy = Mockery::mock(TenancyManager::class);
        $tenancy->shouldReceive('context')->andReturn(new TenantContext(new AccountRef('box', 'box', 'active', [], []), boxed: true));
        $app->instance(TenancyManager::class, $tenancy);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $connection = $this->app['db']->connection();

        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
            throw new RuntimeException('Bridge tests require isolated SQLite :memory:.');
        }
        $connection->getSchemaBuilder()->create('users', static function (Blueprint $table): void {
            $table->id();
        });
        $connection->getSchemaBuilder()->create('projects', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('owner_id');
        });
    }
}
