<?php

declare(strict_types=1);

namespace CoreX\Media\Tests;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\MediaServiceProvider;
use CoreX\Time\Laravel\TimeRegistrar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use Packman\PackmanServiceProvider;
use Packman\Schema\Laravel\SchemaServiceProvider;
use Packman\Schema\Testing\MigratesSchemaSets;
use Vaulter\Kernel;

abstract class TestCase extends Orchestra
{
    use MigratesSchemaSets;

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PackmanServiceProvider::class, SchemaServiceProvider::class, MediaServiceProvider::class];
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('vaulter.database.connection', 'sqlite');
        $app['config']->set('vaulter-media.disk', 'media');
        $app['config']->set('vaulter.schedule.enabled', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->migrateSchemaSets(['axiomasoft/corex-media:tenant'], DB::getDefaultConnection());
        $this->artisan('migrate', ['--database' => DB::getDefaultConnection(), '--force' => true])->assertExitCode(0);
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(Kernel::class)->assertValid();
        (new TimeRegistrar)->register($this->app);
        Storage::fake('media');
        $resolver = Mockery::mock(TenantContextResolver::class);
        $resolver->shouldReceive('current')->andReturn(new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'media-test', 'active', [], [])));
        $this->app->instance(TenantContextResolver::class, $resolver);
        $this->app->forgetScopedInstances();
        $this->app->forgetInstance(MediaPolicy::class);
    }
}
