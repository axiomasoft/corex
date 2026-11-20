<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\NamedWorkPrincipal;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Exceptions\ScopeAuthorityUnavailable;
use CoreX\Scheduling\Contracts\DueWakeupSweeper;
use CoreX\Scheduling\Contracts\WakeupScheduleHint;
use CoreX\Tenancy\Integration\Internal\Wakeup\RootRegistryWakeupHint;
use CoreX\Tenancy\Integration\Internal\Wakeup\RootWakeupReconciler;
use CoreX\Tenancy\Integration\Internal\Wakeup\SweepRootWakeups;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use Illuminate\Console\Events\ArtisanStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WakeupRegistrar implements PackageRegistrar
{
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
        $app->tag(RootRegistryWakeupHint::class, WakeupScheduleHint::TAG);
        $app->scoped(
            RootWakeupReconciler::class,
            static fn (Application $app): RootWakeupReconciler => new RootWakeupReconciler(
                connections: $app->make(ConnectionResolverInterface::class),
                rootConnection: $app->make(TenancyOptions::class)->wakeupRootConnection,
                clock: $app->make(ClockInterface::class),
                hint: $app->make(RootRegistryWakeupHint::class),
                tenancy: $app->make(TenancyManager::class),
                namedPrincipals: $app->bound(NamedWorkPrincipal::class)
                    ? $app->make(NamedWorkPrincipal::class)
                    : throw new ScopeAuthorityUnavailable('Root wakeup requires a host Scheduler service grant.'),
                scopes: $app->make(DataScopeFactory::class),
                sweeper: $app->make(DueWakeupSweeper::class),
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

        $app->make(Dispatcher::class)->listen(ArtisanStarting::class, static fn (ArtisanStarting $event) => $event->artisan->resolveCommands([SweepRootWakeups::class]));
    }
}
