<?php

declare(strict_types=1);

namespace CoreX\Media\Tests;

use CoreX\Database\Laravel\DatabaseRegistrar;
use CoreX\Database\Testing\MigratesSchemaSets;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\MediaServiceProvider;
use CoreX\Storage\Contracts\TenantStorage;
use CoreX\Time\Laravel\TimeRegistrar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;

abstract class TestCase extends Orchestra
{
    use MigratesSchemaSets;

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [MediaServiceProvider::class];
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        (new DatabaseRegistrar)->register($app);
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->migrateSchemaSets(['axiomasoft/corex-media:tenant'], DB::getDefaultConnection());
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        (new TimeRegistrar)->register($this->app);
        Storage::fake('media');
        $storage = Mockery::mock(TenantStorage::class);
        $storage->shouldReceive('disk')->andReturn(Storage::disk('media'));
        $this->app->instance(TenantStorage::class, $storage);
        $this->app->forgetScopedInstances();
        $this->app->instance(TenantStorage::class, $storage);
        $this->app->forgetInstance(MediaPolicy::class);
    }
}
