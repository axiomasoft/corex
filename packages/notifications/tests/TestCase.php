<?php

declare(strict_types=1);

namespace CoreX\Notifications\Tests;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Notifications\Internal\NotificationsOptions;
use CoreX\Notifications\NotificationsServiceProvider;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/../../wakeup/tests/Support/PackageBridge.php';
use Orchestra\Testbench\TestCase as Orchestra;
use Override;

abstract class TestCase extends Orchestra
{
    #[Override]
    protected function getPackageProviders($app): array
    {
        // Package registration runs before defineEnvironment, and the digest tags are chosen then.
        $app['config']->set('corex-notifications.digest.enabled', true);
        $app['config']->set('corex-notifications.digest.accept_legacy_unstamped', false);

        return [wakeup_bridge_provider(), NotificationsServiceProvider::class];
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('corex-notifications.digest.enabled', true);
        $app['config']->set('corex-notifications.digest.accept_legacy_unstamped', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::dropIfExists('ntf_deliveries');
        Schema::dropIfExists('ntf_recipient_state');
        Schema::dropIfExists('ntf_notifications');
        Schema::dropIfExists('sys_wakeup_requests');
        $this->loadMigrationsFrom(__DIR__.'/../../wakeup/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TenantContextResolver::class, new NotificationTenantContextResolver);
        $this->app->forgetInstance(NotificationsOptions::class);
        $this->app->forgetScopedInstances();
    }
}
