<?php

declare(strict_types=1);

namespace CoreX\Notifications\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\RuntimeConfigCheck;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Notifications\Delivery\Contracts\NotificationChannelDriver;
use CoreX\Notifications\Delivery\Contracts\RecipientResolver;
use CoreX\Notifications\Delivery\Internal\ChannelDrivers;
use CoreX\Notifications\Delivery\Internal\DeliveryCoordinator;
use CoreX\Notifications\Delivery\Internal\DeliveryStore;
use CoreX\Notifications\Delivery\Internal\InAppChannelDriver;
use CoreX\Notifications\Delivery\Internal\NullRecipientResolver;
use CoreX\Notifications\Delivery\Laravel\DeliveryPortBindings;
use CoreX\Notifications\Inbox\Contracts\NotificationCenter;
use CoreX\Notifications\Inbox\Contracts\NotificationPolicy;
use CoreX\Notifications\Inbox\Internal\DenyNotificationPolicy;
use CoreX\Notifications\Inbox\Internal\SqlNotificationCenter;
use CoreX\Notifications\Internal\DangerousModesCheck;
use CoreX\Notifications\Internal\DigestSchedulerCheck;
use CoreX\Notifications\Internal\NotificationsOptions;
use CoreX\Notifications\Preferences\Contracts\PreferenceRepository;
use CoreX\Notifications\Preferences\Internal\SettingsPreferenceRepository;
use CoreX\Settings\Contracts\SettingsRepository;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Override;
use Packman\Foundation\Contracts\PackageRegistrar;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class NotificationsBindingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        DeliveryPortBindings::register($app);

        $app->singleton(NotificationsOptions::class, static fn (Application $app): NotificationsOptions => NotificationsOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-notifications', []),
        ));
        $app->tag([DangerousModesCheck::class, DigestSchedulerCheck::class], RuntimeConfigCheck::class);
        $app->singleton(NotificationPolicy::class, DenyNotificationPolicy::class);
        $app->singleton(InAppChannelDriver::class);
        $app->tag(InAppChannelDriver::class, 'corex.notifications.channels');
        $app->singleton(RecipientResolver::class, NullRecipientResolver::class);
        $app->scoped(ChannelDrivers::class, static fn (Application $app): ChannelDrivers => new ChannelDrivers(self::channelDrivers($app)));
        $app->scoped(DeliveryStore::class, static fn (Application $app): DeliveryStore => new DeliveryStore(
            connection: $app->make(DatabaseManager::class)->connection(),
            claimSeconds: $app->make(NotificationsOptions::class)->deliveryClaimSeconds,
            maxAttempts: $app->make(NotificationsOptions::class)->deliveryMaxAttempts,
            clock: $app->make(ClockInterface::class),
        ));
        $app->scoped(NotificationCenter::class, static fn (Application $app): SqlNotificationCenter => new SqlNotificationCenter(
            connection: $app->make(DatabaseManager::class)->connection(),
            policy: $app->make(NotificationPolicy::class),
            clock: $app->make(ClockInterface::class),
        ));
        $app->scoped(PreferenceRepository::class, static fn (Application $app): SettingsPreferenceRepository => new SettingsPreferenceRepository(
            connection: $app->make(DatabaseManager::class)->connection(),
            settings: $app->make(SettingsRepository::class),
        ));
        $app->scoped(DeliveryCoordinator::class, static fn (Application $app): DeliveryCoordinator => new DeliveryCoordinator(
            deliveries: $app->make(DeliveryStore::class),
            drivers: $app->make(ChannelDrivers::class),
            recipients: $app->make(RecipientResolver::class),
            policy: $app->make(NotificationPolicy::class),
            tenants: $app->make(TenantContextResolver::class),
        ));
    }

    #[Override]
    public function boot(Application $app): void
    {
        $app->make(NotificationsOptions::class);
    }

    /** @return iterable<NotificationChannelDriver> */
    private static function channelDrivers(Application $app): iterable
    {
        /** @var iterable<NotificationChannelDriver> $drivers */
        $drivers = $app->tagged('corex.notifications.channels');

        return $drivers;
    }
}
