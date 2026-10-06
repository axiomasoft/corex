<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Tests;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Database\Laravel\DatabaseRegistrar;
use CoreX\Database\Testing\MigratesSchemaSets;
use CoreX\Time\Laravel\LaravelClock;
use CoreX\Wakeup\WakeupServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\DateFactory;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
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
        return [WakeupServiceProvider::class];
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
        $this->migrateSchemaSets(['axiomasoft/corex-wakeup:tenant'], 'sqlite');
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
    }
}
