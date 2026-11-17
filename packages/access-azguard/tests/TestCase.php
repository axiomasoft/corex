<?php

declare(strict_types=1);

namespace CoreX\AccessAzguard\Tests;

use AzGuard\AzGuardServiceProvider;
use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Laravel\AuthOptions;
use CoreX\Access\Laravel\ScopeResolver;
use CoreX\Access\Matrix\Contracts\EntityActivity;
use CoreX\AccessAzguard\AccessAzguardServiceProvider;
use CoreX\AccessAzguard\Tests\Stubs\BridgePanel;
use CoreX\AccessAzguard\Tests\Stubs\Project;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Mockery;
use Orchestra\Testbench\TestCase as Orchestra;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Data\Entities\EntityDefinition;
use Packman\Modules\Testing\ArrayEntityRegistry;
use RuntimeException;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [AzGuardServiceProvider::class, BridgePanel::class, AccessAzguardServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('azguard.cache.namespace', 'corex-bridge-test');
        $app->instance(AuthOptions::class, AuthOptions::fromArray([]));
        $app->bind(AccessChecker::class, static fn () => Mockery::mock(AccessChecker::class));
        $app->bind(ScopeResolver::class, static fn () => Mockery::mock(ScopeResolver::class));
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
