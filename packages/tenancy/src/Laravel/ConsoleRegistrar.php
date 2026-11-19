<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Tenancy\Laravel\Commands\IdentitiesReconcileCommand;
use CoreX\Tenancy\Laravel\Commands\PendingClearCommand;
use CoreX\Tenancy\Laravel\Commands\PendingCreateCommand;
use CoreX\Tenancy\Laravel\Commands\TenantsMigrateCommand;
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
            PendingCreateCommand::class,
            PendingClearCommand::class,
            IdentitiesReconcileCommand::class,
            TenantsMigrateCommand::class,
        ]));
    }
}
