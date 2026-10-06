<?php

declare(strict_types=1);

namespace CoreX\Notifications\Tests;

use CoreX\Database\Laravel\DatabaseRegistrar;
use CoreX\Database\Testing\MigratesSchemaSets;
use CoreX\Notifications\NotificationsServiceProvider;
use CoreX\Time\Laravel\SystemClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use Psr\Clock\ClockInterface;

abstract class DisabledSchedulingCase extends Orchestra
{
    use MigratesSchemaSets;

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [NotificationsServiceProvider::class];
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        (new DatabaseRegistrar)->register($app);
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('corex-notifications.digest.enabled', false);
        $app->singleton(ClockInterface::class, static fn (): SystemClock => new SystemClock);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::dropIfExists('ntf_digest_items');
        Schema::dropIfExists('ntf_digest_runs');
        Schema::dropIfExists('ntf_deliveries');
        Schema::dropIfExists('ntf_recipient_state');
        Schema::dropIfExists('ntf_notifications');
        $this->migrateSchemaSets(['axiomasoft/corex-notifications:tenant'], DB::getDefaultConnection());
    }
}
