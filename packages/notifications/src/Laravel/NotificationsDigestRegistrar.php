<?php

declare(strict_types=1);

namespace CoreX\Notifications\Laravel;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Notifications\Digest\Contracts\DigestBuilder;
use CoreX\Notifications\Internal\NotificationsOptions;
use CoreX\Notifications\Preferences\Contracts\PreferenceRepository;
use CoreX\Notifications\Runtime\Internal\DeliveryIntents;
use CoreX\Notifications\Runtime\Internal\DigestTick;
use CoreX\Notifications\Runtime\Internal\DigestWakeupAdapter;
use CoreX\Notifications\Runtime\Internal\DigestWakeupHandler;
use CoreX\Notifications\Runtime\Internal\NotificationDigestDueSource;
use CoreX\Notifications\Runtime\Internal\SqlDigestBuilder;
use CoreX\Scheduling\Contracts\DueWakeupSource;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\WakeupHandlerDefinition;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class NotificationsDigestRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        // Digest wiring decides what to register, so it needs the shape at register time; this one-off read does not cache Options before boot.
        $options = NotificationsOptions::fromArray((array) $app->make(Repository::class)->get('corex-notifications', []));

        $app->scoped(DigestWakeupHandler::class, static fn (Application $app): DigestWakeupHandler => new DigestWakeupHandler(
            connection: $app->make(DatabaseManager::class)->connection(),
            currentScope: $app->make(CurrentWorkScope::class),
            scopeCodec: $app->make(DataScopeCodec::class),
            preferences: $app->make(PreferenceRepository::class),
            deliveries: $app->make(DeliveryIntents::class),
            digests: $app->make(DigestBuilder::class),
        ));
        $app->singleton(DigestWakeupAdapter::class, static fn (Application $app): DigestWakeupAdapter => new DigestWakeupAdapter(
            // Resolved through the active container at call time: a resident worker replaces it per request, so the
            // scoped handler of the current cycle is used and the registering application is never retained.
            static fn (DigestTick $tick) => (Container::getInstance()->make(DigestWakeupHandler::class))($tick),
        ));

        if ($options->digestEnabled) {
            $app->singleton('corex.notifications.digest-wakeup-handler', static fn (): WakeupHandlerDefinition => new WakeupHandlerDefinition(
                kind: SqlDigestBuilder::HandlerKind,
                handler: DigestWakeupAdapter::class,
                version: SqlDigestBuilder::HandlerVersion,
            ));
            $app->tag('corex.notifications.digest-wakeup-handler', WakeupHandlerDefinition::TAG);
            $app->tag(NotificationDigestDueSource::class, DueWakeupSource::TAG);
        }

        $app->scoped(DigestBuilder::class, static fn (Application $app): SqlDigestBuilder => new SqlDigestBuilder(
            connection: $app->make(DatabaseManager::class)->connection(),
            scopes: $app->make(DataScopeFactory::class),
            currentScope: $app->make(CurrentWorkScope::class),
            scopeCodec: $app->make(DataScopeCodec::class),
            issuer: $app->make(StoredWorkIssuer::class),
            wakeup: $app->make(NotificationsOptions::class)->digestEnabled ? $app->make(WakeupScheduler::class) : null,
            enabled: $app->make(NotificationsOptions::class)->digestEnabled,
            clock: $app->make(ClockInterface::class),
        ));
        $app->scoped(NotificationDigestDueSource::class, static fn (Application $app): NotificationDigestDueSource => new NotificationDigestDueSource(
            connection: $app->make(DatabaseManager::class)->connection(),
            scopes: $app->make(DataScopeCodec::class),
            runner: $app->make(StoredWorkRunner::class),
            currentScopes: $app->make(DataScopeFactory::class),
            current: $app->make(CurrentWorkScope::class),
            scheduler: static fn (): WakeupScheduler => Container::getInstance()->make(WakeupScheduler::class),
        ));

    }

    #[Override]
    public function boot(Application $app): void {}
}
