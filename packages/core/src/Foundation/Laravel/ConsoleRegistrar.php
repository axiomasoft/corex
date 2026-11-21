<?php

declare(strict_types=1);

namespace CoreX\Foundation\Laravel;

use CoreX\Audit\Internal\PruneAuditLog;
use CoreX\Foundation\Internal\Commands\ManageLifecycle;
use CoreX\Messaging\Internal\Commands\PruneOutboxMessages;
use CoreX\Messaging\Internal\Commands\RelayOutbox;
use CoreX\Messaging\Internal\Commands\ShowDeliveryHealth;
use CoreX\Messaging\Internal\PrunePubSubMessages;
use Illuminate\Console\Events\ArtisanStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ConsoleRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void {}

    #[Override]
    public function boot(Application $app): void
    {
        if (! $app->runningInConsole()) {
            return;
        }

        $app->make(Dispatcher::class)->listen(ArtisanStarting::class, static fn (ArtisanStarting $event) => $event->artisan->resolveCommands([
            ManageLifecycle::class,
            PruneAuditLog::class,
            PrunePubSubMessages::class,
            PruneOutboxMessages::class,
            RelayOutbox::class,
            ShowDeliveryHealth::class,
        ]));
    }
}
