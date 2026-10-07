<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Tenancy\Integration\Internal\Wakeup\RootRegistryWakeupHint;
use CoreX\Tenancy\Integration\Internal\Wakeup\RootWakeupReconciler;
use CoreX\Tenancy\Integration\Internal\Wakeup\SweepRootWakeups;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use Illuminate\Console\Application as Artisan;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Override;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WakeupRegistrar implements PackageRegistrar
{
    private const string WAKEUP_SWEEPER = 'CoreX\\Wakeup\\Contracts\\DueWakeupSweeper';

    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(
            RootRegistryWakeupHint::class,
            static fn (Application $app): RootRegistryWakeupHint => new RootRegistryWakeupHint(
                connections: $app->make(ConnectionResolverInterface::class),
                rootConnection: $app->make(TenancyOptions::class)->wakeupRootConnection,
                clock: $app->make(ClockInterface::class),
                logger: $app->make(LoggerInterface::class),
            ),
        );
        $app->tag(RootRegistryWakeupHint::class, 'corex.wakeup.hints');
        $app->scoped(
            RootWakeupReconciler::class,
            static fn (Application $app): RootWakeupReconciler => new RootWakeupReconciler(
                connections: $app->make(ConnectionResolverInterface::class),
                rootConnection: $app->make(TenancyOptions::class)->wakeupRootConnection,
                clock: $app->make(ClockInterface::class),
                hint: $app->make(RootRegistryWakeupHint::class),
                tenancy: $app->make(TenancyManager::class),
                sweeper: $app->make(self::WAKEUP_SWEEPER),
                leaseSeconds: $app->make(TenancyOptions::class)->wakeupLeaseSeconds,
            ),
        );
    }

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
        $artisan->resolveCommands([SweepRootWakeups::class]);
    }
}
