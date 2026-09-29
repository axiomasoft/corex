<?php

declare(strict_types=1);

namespace CoreX\Wakeup;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Wakeup\Commands\SweepWakeups;
use CoreX\Wakeup\Commands\ValidateWakeupStamps;
use CoreX\Wakeup\Contracts\DueWakeupSource;
use CoreX\Wakeup\Contracts\DueWakeupSweeper;
use CoreX\Wakeup\Contracts\WakeupScheduleHint;
use CoreX\Wakeup\Contracts\WakeupScheduler;
use CoreX\Wakeup\Data\WakeupHandlerDefinition;
use CoreX\Wakeup\Internal\SqlWakeupScheduler;
use CoreX\Wakeup\Internal\SweepRunner;
use CoreX\Wakeup\Internal\SystemClock;
use CoreX\Wakeup\Internal\WakeupHandlerRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;
use Psr\Clock\ClockInterface;
use RuntimeException;

final class WakeupServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-wakeup.php', 'corex-wakeup');

        if (! $this->app->bound(ClockInterface::class)) {
            $this->app->singleton(ClockInterface::class, static fn (): SystemClock => new SystemClock);
        }
        $this->app->singleton(
            WakeupHandlerRegistry::class,
            fn (Application $app): WakeupHandlerRegistry => WakeupHandlerRegistry::compile(
                definitions: $this->handlerDefinitions($app),
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
            leaseSeconds: (int) $app['config']->get('corex-wakeup.lease_seconds'),
            maxAttempts: (int) $app['config']->get('corex-wakeup.max_attempts'),
            clock: $app->make(ClockInterface::class),
        ));

        if ($this->app->runningInConsole()) {
            $this->commands([SweepWakeups::class, ValidateWakeupStamps::class]);
        }
    }

    /** @return list<WakeupHandlerDefinition> */
    private function handlerDefinitions(Application $app): array
    {
        $definitions = [];

        foreach ((array) $app['config']->get('corex-wakeup.handlers', []) as $kind => $configured) {
            if (! is_string($kind)) {
                throw new RuntimeException('Wakeup handler definitions must be keyed by kind.');
            }

            if (is_string($configured)) {
                trigger_error(
                    "Scalar wakeup handler config [{$kind}] is deprecated; configure handler and version explicitly.",
                    E_USER_DEPRECATED,
                );
                $definitions[] = new WakeupHandlerDefinition(
                    kind: $kind,
                    version: 'legacy-class/1:'.hash('sha256', $configured),
                    handler: $configured,
                );

                continue;
            }

            if (! is_array($configured)
                || ! is_string($configured['handler'] ?? null)
                || ! is_string($configured['version'] ?? null)
                || (isset($configured['accept_legacy_unstamped']) && ! is_bool($configured['accept_legacy_unstamped']))) {
                throw new RuntimeException('Wakeup handler definitions require handler, version and an optional boolean accept_legacy_unstamped.');
            }

            $definitions[] = new WakeupHandlerDefinition(
                kind: $kind,
                version: $configured['version'],
                handler: $configured['handler'],
                acceptLegacyUnstamped: $configured['accept_legacy_unstamped'] ?? false,
            );
        }

        return $definitions;
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
