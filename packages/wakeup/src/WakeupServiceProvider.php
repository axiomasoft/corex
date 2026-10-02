<?php

declare(strict_types=1);

namespace CoreX\Wakeup;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\RuntimeConfigCheck;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Wakeup\Contracts\DueWakeupSource;
use CoreX\Wakeup\Contracts\DueWakeupSweeper;
use CoreX\Wakeup\Contracts\WakeupScheduleHint;
use CoreX\Wakeup\Contracts\WakeupScheduler;
use CoreX\Wakeup\Internal\Commands\SweepWakeups;
use CoreX\Wakeup\Internal\Commands\ValidateWakeupStamps;
use CoreX\Wakeup\Internal\DangerousModesCheck;
use CoreX\Wakeup\Internal\SqlWakeupScheduler;
use CoreX\Wakeup\Internal\SweepRunner;
use CoreX\Wakeup\Internal\SystemClock;
use CoreX\Wakeup\Internal\WakeupHandlerRegistry;
use CoreX\Wakeup\Internal\WakeupOptions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;
use Psr\Clock\ClockInterface;
use RuntimeException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WakeupServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-wakeup.php', 'corex-wakeup');
        $this->app->singleton(WakeupOptions::class, static fn (Application $app): WakeupOptions => WakeupOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-wakeup', []),
        ));
        $this->app->tag([DangerousModesCheck::class], RuntimeConfigCheck::class);

        if (! $this->app->bound(ClockInterface::class)) {
            $this->app->singleton(ClockInterface::class, static fn (): SystemClock => new SystemClock);
        }
        $this->app->singleton(
            WakeupHandlerRegistry::class,
            fn (Application $app): WakeupHandlerRegistry => WakeupHandlerRegistry::compile(
                definitions: $app->make(WakeupOptions::class)->handlers,
                resolve: fn (string $handler) => $app->make($handler),
                registryFingerprint: $this->registryFingerprint($app),
            ),
        );
        $this->app->scoped(
            WakeupScheduler::class,
            fn (Application $app): SqlWakeupScheduler => new SqlWakeupScheduler(
                connection: $app['db']->connection(),
                contexts: $app->make(TenantContextResolver::class),
                handlers: $app->make(WakeupHandlerRegistry::class),
                hints: $this->hints($app),
                clock: $app->make(ClockInterface::class),
            ),
        );
        $this->app->scoped(DueWakeupSweeper::class, fn (Application $app): SweepRunner => new SweepRunner(
            connection: $app['db']->connection(),
            contexts: $app->make(TenantContextResolver::class),
            handlers: $app->make(WakeupHandlerRegistry::class),
            dueSources: $this->dueSources($app),
            leaseSeconds: $app->make(WakeupOptions::class)->leaseSeconds,
            maxAttempts: $app->make(WakeupOptions::class)->maxAttempts,
            clock: $app->make(ClockInterface::class),
        ));

        if ($this->app->runningInConsole()) {
            $this->commands([SweepWakeups::class, ValidateWakeupStamps::class]);
        }
    }

    public function boot(): void
    {
        $this->app->make(WakeupOptions::class);
    }

    private function registryFingerprint(Application $app): string
    {
        if (! $app->bound(ModuleRegistry::class)) {
            return WakeupHandlerRegistry::ModuleFree;
        }

        return $app->make(ModuleRegistry::class)->compiled()->fingerprint;
    }

    /** @return list<DueWakeupSource> */
    private function dueSources(Application $app): array
    {
        $sources = [];

        foreach ($app->tagged('corex.wakeup.due-sources') as $source) {
            if (! $source instanceof DueWakeupSource) {
                throw new RuntimeException('Wakeup due sources must implement DueWakeupSource.');
            }

            $sources[] = $source;
        }

        return $sources;
    }

    /** @return list<WakeupScheduleHint> */
    private function hints(Application $app): array
    {
        $hints = [];

        foreach ($app->tagged('corex.wakeup.hints') as $hint) {
            if (! $hint instanceof WakeupScheduleHint) {
                throw new RuntimeException('Wakeup hints must implement WakeupScheduleHint.');
            }

            $hints[] = $hint;
        }

        return $hints;
    }
}
