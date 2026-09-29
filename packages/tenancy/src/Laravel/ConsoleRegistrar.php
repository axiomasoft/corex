<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Tenancy\Laravel\Commands\IdentitiesReconcileCommand;
use CoreX\Tenancy\Laravel\Commands\PendingClearCommand;
use CoreX\Tenancy\Laravel\Commands\PendingCreateCommand;
use CoreX\Tenancy\Laravel\Commands\TenantsMigrateCommand;
use Illuminate\Console\Application as Artisan;
use Illuminate\Contracts\Foundation\Application;
use Override;

/** @internal Composition registrar. Not package public API. */
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

        Artisan::starting(static function (Artisan $artisan): void {
            $artisan->resolveCommands([
                PendingCreateCommand::class,
                PendingClearCommand::class,
                IdentitiesReconcileCommand::class,
                TenantsMigrateCommand::class,
            ]);
        });
    }
}
