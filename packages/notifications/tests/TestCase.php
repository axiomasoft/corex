<?php

declare(strict_types=1);

namespace CoreX\Notifications\Tests;

use CoreX\Notifications\Internal\Digest\DigestWakeupAdapter;
use CoreX\Notifications\Internal\Digest\NotificationDigestDueSource;
use CoreX\Notifications\NotificationsServiceProvider;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\TenantContext;
use CoreX\Wakeup\WakeupServiceProvider;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;

abstract class TestCase extends Orchestra
{
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [WakeupServiceProvider::class, NotificationsServiceProvider::class];
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('queue.default', 'sync');
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
        $this->app['config']->set('corex-notifications.digest.enabled', true);
        $this->app['config']->set('corex-notifications.digest.accept_legacy_unstamped', false);
        $this->app['config']->set('corex-wakeup.handlers', [
            'notification.digest.v1' => [
                'handler' => DigestWakeupAdapter::class,
                'version' => 'notification-digest/1',
                'accept_legacy_unstamped' => false,
            ],
        ]);
        $this->app->tag(NotificationDigestDueSource::class, 'corex.wakeup.due-sources');
        $this->app->forgetScopedInstances();
    }
}

final class NotificationTenantContextResolver implements TenantContextResolver
{
    public function current(): TenantContext
    {
        return new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'notification-test', 'active', [], []));
    }
}
