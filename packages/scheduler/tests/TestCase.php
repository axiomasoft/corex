<?php

declare(strict_types=1);

namespace CoreX\Scheduler\Tests;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\CoreServiceProvider;
use CoreX\Scheduler\SchedulerServiceProvider;
use CoreX\Tests\Support\WorkScopeFixture;
use CoreX\Time\Laravel\LaravelClock;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\DateFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use Packman\PackmanServiceProvider;
use Packman\Schema\Laravel\SchemaServiceProvider;
use Packman\Schema\Testing\MigratesSchemaSets;
use Psr\Clock\ClockInterface;

final class TestTenantContextResolver implements TenantContextResolver
{
    public function current(): TenantContext
    {
        return new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'wakeup-test', 'active', [], []));
    }
}

abstract class TestCase extends Orchestra
{
    use MigratesSchemaSets;

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PackmanServiceProvider::class, SchemaServiceProvider::class, CoreServiceProvider::class, SchedulerServiceProvider::class];
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->migrateSchemaSets(['axiomasoft/corex-scheduler:tenant'], DB::getDefaultConnection());
        Schema::create('wakeup_effects', function (Blueprint $table): void {
            $table->string('request_key')->primary();
        });
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->singleton(ClockInterface::class, static fn ($app): LaravelClock => new LaravelClock(
            dates: $app->make(DateFactory::class),
        ));
        $this->app->instance(TenantContextResolver::class, new TestTenantContextResolver);
        WorkScopeFixture::install($this->app)->scope();
    }
}
