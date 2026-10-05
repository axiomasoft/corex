<?php

declare(strict_types=1);

namespace CoreX\Notifications\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Notifications\Digest\Contracts\DigestBuilder;
use CoreX\Notifications\Digest\Internal\DigestTick;
use CoreX\Notifications\Digest\Internal\DigestWakeupAdapter;
use CoreX\Notifications\Digest\Internal\DigestWakeupHandler;
use CoreX\Notifications\Digest\Internal\NotificationDigestDueSource;
use CoreX\Notifications\Digest\Internal\SqlDigestBuilder;
use CoreX\Notifications\Internal\NotificationsOptions;
use CoreX\Notifications\Preferences\Contracts\PreferenceRepository;
use CoreX\Scheduling\Contracts\DueWakeupSource;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\WakeupHandlerDefinition;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Override;
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
            preferences: $app->make(PreferenceRepository::class),
            clock: $app->make(ClockInterface::class),
        ));
        $app->singleton(DigestWakeupAdapter::class, static fn (Application $app): DigestWakeupAdapter => new DigestWakeupAdapter(
            // Resolved through the active container at call time: a resident worker replaces it per request, so the
            // scoped handler of the current cycle is used and the registering application is never retained.
            static fn (DigestTick $tick) => (Container::getInstance()->make(DigestWakeupHandler::class))($tick),
        ));

        if ($options->digestEnabled) {
            $app->singleton('corex.notifications.digest-wakeup-handler', static fn (Application $app): WakeupHandlerDefinition => new WakeupHandlerDefinition(
                kind: 'notification.digest.v1',
                handler: DigestWakeupAdapter::class,
                version: 'notification-digest/1',
                acceptLegacyUnstamped: $app->make(NotificationsOptions::class)->digestAcceptLegacyUnstamped,
            ));
            $app->tag('corex.notifications.digest-wakeup-handler', WakeupHandlerDefinition::TAG);
            $app->tag(NotificationDigestDueSource::class, DueWakeupSource::TAG);
        }

        $app->scoped(DigestBuilder::class, static fn (Application $app): SqlDigestBuilder => new SqlDigestBuilder(
            connection: $app->make(DatabaseManager::class)->connection(),
            wakeup: $app->make(NotificationsOptions::class)->digestEnabled ? $app->make(WakeupScheduler::class) : null,
            enabled: $app->make(NotificationsOptions::class)->digestEnabled,
            clock: $app->make(ClockInterface::class),
        ));
        $app->scoped(NotificationDigestDueSource::class, static fn (Application $app): NotificationDigestDueSource => new NotificationDigestDueSource(
            connection: $app->make(DatabaseManager::class)->connection(),
            digests: $app->make(DigestBuilder::class),
            handler: $app->make(DigestWakeupHandler::class),
        ));

    }

    #[Override]
    public function boot(Application $app): void {}
}
