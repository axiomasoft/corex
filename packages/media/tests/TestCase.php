<?php

declare(strict_types=1);

namespace CoreX\Media\Tests;

use CoreX\Contracts\TenantStorage;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\MediaServiceProvider;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;

abstract class TestCase extends Orchestra
{
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [MediaServiceProvider::class];
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        $storage = Mockery::mock(TenantStorage::class);
        $storage->shouldReceive('disk')->andReturn(Storage::disk('media'));
        $this->app->instance(TenantStorage::class, $storage);
        $this->app->forgetScopedInstances();
        $this->app->instance(TenantStorage::class, $storage);
        $this->app->forgetInstance(MediaPolicy::class);
    }
}
