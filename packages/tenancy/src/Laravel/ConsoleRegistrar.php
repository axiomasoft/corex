<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\Laravel\Commands\IdentitiesReconcileCommand;
use CoreX\Tenancy\Laravel\Commands\PendingClearCommand;
use CoreX\Tenancy\Laravel\Commands\PendingCreateCommand;
use CoreX\Tenancy\Laravel\Commands\TenantsMigrateCommand;
use Illuminate\Console\Application as Artisan;
use Illuminate\Contracts\Foundation\Application;
use Override;
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

        Artisan::starting(self::registerCommands(...));
    }

    private static function registerCommands(Artisan $artisan): void
    {
        $artisan->resolveCommands([
            PendingCreateCommand::class,
            PendingClearCommand::class,
            IdentitiesReconcileCommand::class,
            TenantsMigrateCommand::class,
        ]);
    }
}
