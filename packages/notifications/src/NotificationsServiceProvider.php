<?php

declare(strict_types=1);

namespace CoreX\Notifications;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Notifications\Delivery\Contracts\RecipientResolver;
use CoreX\Notifications\Delivery\Internal\ChannelDrivers;
use CoreX\Notifications\Delivery\Internal\Commands\SweepNotificationDeliveries;
use CoreX\Notifications\Delivery\Internal\DeliveryCoordinator;
use CoreX\Notifications\Delivery\Internal\DeliveryStore;
use CoreX\Notifications\Delivery\Internal\InAppChannelDriver;
use CoreX\Notifications\Delivery\Internal\NullRecipientResolver;
use CoreX\Notifications\Delivery\Laravel\DeliveryPortBindings;
use CoreX\Notifications\Digest\Contracts\DigestBuilder;
use CoreX\Notifications\Digest\Internal\DigestTick;
use CoreX\Notifications\Digest\Internal\DigestWakeupAdapter;
use CoreX\Notifications\Digest\Internal\DigestWakeupHandler;
use CoreX\Notifications\Digest\Internal\NotificationDigestDueSource;
use CoreX\Notifications\Digest\Internal\SqlDigestBuilder;
use CoreX\Notifications\Inbox\Contracts\NotificationCenter;
use CoreX\Notifications\Inbox\Contracts\NotificationPolicy;
use CoreX\Notifications\Inbox\Internal\DenyNotificationPolicy;
use CoreX\Notifications\Inbox\Internal\SqlNotificationCenter;
use CoreX\Notifications\Preferences\Contracts\PreferenceRepository;
use CoreX\Notifications\Preferences\Internal\SettingsPreferenceRepository;
use CoreX\Settings\Contracts\SettingsRepository;
use CoreX\Wakeup\Contracts\WakeupScheduler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class NotificationsServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        DeliveryPortBindings::register($this->app);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-notifications.php', 'corex-notifications');

        $this->app->singleton(DigestWakeupHandler::class, static fn (Application $app): DigestWakeupHandler => new DigestWakeupHandler(
            connection: $app['db']->connection(),
            preferences: $app->make(PreferenceRepository::class),
        ));
        $this->app->singleton(DigestWakeupAdapter::class, static fn (Application $app): DigestWakeupAdapter => new DigestWakeupAdapter(static function (DigestTick $tick) use ($app): void {
            ($app->make(DigestWakeupHandler::class))($tick);
        }));

        if ((bool) $this->app['config']->get('corex-notifications.digest.enabled')) {
            $handlers = (array) $this->app['config']->get('corex-wakeup.handlers', []);
            $handlers['notification.digest.v1'] = [
                'handler' => DigestWakeupAdapter::class,
                'version' => 'notification-digest/1',
                'accept_legacy_unstamped' => (bool) $this->app['config']->get('corex-notifications.digest.accept_legacy_unstamped', false),
            ];
            $this->app['config']->set('corex-wakeup.handlers', $handlers);
        }
        $this->app->singleton(NotificationPolicy::class, DenyNotificationPolicy::class);
        $this->app->singleton(InAppChannelDriver::class);
        $this->app->tag(InAppChannelDriver::class, 'corex.notifications.channels');
        $this->app->singleton(RecipientResolver::class, NullRecipientResolver::class);
        $this->app->scoped(ChannelDrivers::class, static fn (Application $app): ChannelDrivers => new ChannelDrivers($app->tagged('corex.notifications.channels')));
        $this->app->scoped(DeliveryStore::class, static fn (Application $app): DeliveryStore => new DeliveryStore(
            connection: $app['db']->connection(),
            claimSeconds: (int) $app['config']->get('corex-notifications.delivery.claim_seconds'),
            maxAttempts: (int) $app['config']->get('corex-notifications.delivery.max_attempts'),
        ));
        $this->app->scoped(NotificationCenter::class, static fn (Application $app): SqlNotificationCenter => new SqlNotificationCenter(
            connection: $app['db']->connection(),
            policy: $app->make(NotificationPolicy::class),
        ));
        $this->app->scoped(DigestBuilder::class, static fn (Application $app): SqlDigestBuilder => new SqlDigestBuilder(
            connection: $app['db']->connection(),
            wakeup: $app->make(WakeupScheduler::class),
            enabled: (bool) $app['config']->get('corex-notifications.digest.enabled'),
        ));

        $this->app->scoped(NotificationDigestDueSource::class, static fn (Application $app): NotificationDigestDueSource => new NotificationDigestDueSource(
            connection: $app['db']->connection(),
            digests: $app->make(DigestBuilder::class),
            handler: $app->make(DigestWakeupHandler::class),
        ));

        if ((bool) $this->app['config']->get('corex-notifications.digest.enabled')) {
            $this->app->tag(NotificationDigestDueSource::class, 'corex.wakeup.due-sources');
        }
        $this->app->scoped(PreferenceRepository::class, static fn (Application $app): SettingsPreferenceRepository => new SettingsPreferenceRepository(
            connection: $app['db']->connection(),
            settings: $app->make(SettingsRepository::class),
        ));
        $this->app->scoped(DeliveryCoordinator::class, static fn (Application $app): DeliveryCoordinator => new DeliveryCoordinator(
            deliveries: $app->make(DeliveryStore::class),
            drivers: $app->make(ChannelDrivers::class),
            recipients: $app->make(RecipientResolver::class),
            policy: $app->make(NotificationPolicy::class),
            tenants: $app->make(TenantContextResolver::class),
        ));

        if ($this->app->runningInConsole()) {
            $this->commands([SweepNotificationDeliveries::class]);
        }
    }
}
