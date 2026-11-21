<?php

declare(strict_types=1);

namespace CoreX\Notifications\Laravel;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\ProviderEffectRecovery;
use CoreX\Context\Contracts\ProviderReceiptProjector;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Notifications\Delivery\Contracts\NotificationChannelDriver;
use CoreX\Notifications\Delivery\Contracts\RecipientResolver;
use CoreX\Notifications\Delivery\Laravel\DeliveryPortBindings;
use CoreX\Notifications\Inbox\Contracts\NotificationCenter;
use CoreX\Notifications\Inbox\Contracts\NotificationPolicy;
use CoreX\Notifications\Internal\DigestSchedulerCheck;
use CoreX\Notifications\Internal\NotificationsOptions;
use CoreX\Notifications\Preferences\Contracts\PreferenceRepository;
use CoreX\Notifications\Runtime\Internal\ChannelDrivers;
use CoreX\Notifications\Runtime\Internal\DeliveryCoordinator;
use CoreX\Notifications\Runtime\Internal\DeliveryIntents;
use CoreX\Notifications\Runtime\Internal\DeliveryReceiptProjector;
use CoreX\Notifications\Runtime\Internal\DeliveryStore;
use CoreX\Notifications\Runtime\Internal\DenyNotificationPolicy;
use CoreX\Notifications\Runtime\Internal\InAppChannelDriver;
use CoreX\Notifications\Runtime\Internal\NotificationModuleReferences;
use CoreX\Notifications\Runtime\Internal\NotificationScope;
use CoreX\Notifications\Runtime\Internal\NotificationVisibility;
use CoreX\Notifications\Runtime\Internal\NullRecipientResolver;
use CoreX\Notifications\Runtime\Internal\SettingsPreferenceRepository;
use CoreX\Notifications\Runtime\Internal\SqlNotificationCenter;
use CoreX\Settings\Contracts\SettingsRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Config\Contracts\RuntimeConfigCheck;
use Packman\Foundation\Contracts\PackageRegistrar;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class NotificationsBindingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->scoped(NotificationModuleReferences::class);
        $app->tag(NotificationModuleReferences::class, ModuleReferences::class);

        DeliveryPortBindings::register($app);

        $app->singleton(NotificationsOptions::class, static fn (Application $app): NotificationsOptions => NotificationsOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-notifications', []),
        ));
        $app->tag([DigestSchedulerCheck::class], RuntimeConfigCheck::class);
        $app->singleton(NotificationPolicy::class, DenyNotificationPolicy::class);
        $app->singleton(InAppChannelDriver::class);
        $app->tag(InAppChannelDriver::class, 'corex.notifications.channels');
        $app->singleton(RecipientResolver::class, NullRecipientResolver::class);
        $app->scoped(ChannelDrivers::class, static fn (Application $app): ChannelDrivers => new ChannelDrivers(self::channelDrivers($app)));
        $app->scoped(DeliveryIntents::class, static fn (Application $app): DeliveryIntents => new DeliveryIntents(
            connection: $app->make(DatabaseManager::class)->connection(),
            current: $app->make(CurrentWorkScope::class),
            scopes: $app->make(DataScopeCodec::class),
            issuer: $app->make(StoredWorkIssuer::class),
            drivers: $app->make(ChannelDrivers::class),
            clock: $app->make(ClockInterface::class),
        ));
        $app->scoped(DeliveryReceiptProjector::class);
        $app->tag(DeliveryReceiptProjector::class, ProviderReceiptProjector::class);
        $app->scoped(DeliveryStore::class, static fn (Application $app): DeliveryStore => new DeliveryStore(
            connection: $app->make(DatabaseManager::class)->connection(),
            claimSeconds: $app->make(NotificationsOptions::class)->deliveryClaimSeconds,
            maxAttempts: $app->make(NotificationsOptions::class)->deliveryMaxAttempts,
            clock: $app->make(ClockInterface::class),
            current: $app->make(CurrentWorkScope::class),
            scopes: $app->make(DataScopeFactory::class),
            codec: $app->make(DataScopeCodec::class),
            tenants: $app->make(TenantContextResolver::class),
            receiptRecovery: static fn (): ProviderEffectRecovery => Container::getInstance()->make(ProviderEffectRecovery::class),
        ));
        $app->scoped(NotificationCenter::class, static fn (Application $app): SqlNotificationCenter => new SqlNotificationCenter(
            connection: $app->make(DatabaseManager::class)->connection(),
            policy: $app->make(NotificationPolicy::class),
            clock: $app->make(ClockInterface::class),
            scopes: $app->make(NotificationScope::class),
            current: $app->make(CurrentWorkScope::class),
            visibility: $app->make(NotificationVisibility::class),
            deliveries: $app->make(DeliveryIntents::class),
            encryption: $app->make(StringEncrypter::class),
        ));
        $app->scoped(PreferenceRepository::class, static fn (Application $app): SettingsPreferenceRepository => new SettingsPreferenceRepository(
            connection: $app->make(DatabaseManager::class)->connection(),
            settings: $app->make(SettingsRepository::class),
            scopes: $app->make(NotificationScope::class),
            current: $app->make(CurrentWorkScope::class),
        ));
        $app->scoped(DeliveryCoordinator::class, static fn (Application $app): DeliveryCoordinator => new DeliveryCoordinator(
            deliveries: $app->make(DeliveryStore::class),
            drivers: $app->make(ChannelDrivers::class),
            recipients: $app->make(RecipientResolver::class),
            policy: $app->make(NotificationPolicy::class),
            visibility: $app->make(NotificationVisibility::class),
            work: $app->make(StoredWorkRunner::class),
            container: $app->make(Container::class),
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
