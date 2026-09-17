<?php

declare(strict_types=1);

namespace CoreX\Wakeup;

use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Wakeup\Commands\SweepWakeups;
use CoreX\Wakeup\Contracts\DueSource;
use CoreX\Wakeup\Contracts\WakeupHandler;
use CoreX\Wakeup\Contracts\WakeupHint;
use CoreX\Wakeup\Contracts\WakeupScheduler;
use CoreX\Wakeup\Contracts\WakeupSweeper;
use CoreX\Wakeup\Internal\SqlWakeupScheduler;
use CoreX\Wakeup\Internal\SweepRunner;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;
use RuntimeException;

final class WakeupServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-wakeup.php', 'corex-wakeup');
        $this->app->scoped(
            WakeupScheduler::class,
            fn (Application $app): SqlWakeupScheduler => new SqlWakeupScheduler(
                connection: $app['db']->connection(),
                contexts: $app->make(TenantContextResolver::class),
                handlers: $this->handlers($app),
                hints: $this->hints($app),
            ),
        );
        $this->app->scoped(WakeupSweeper::class, fn (Application $app): SweepRunner => new SweepRunner($app['db']->connection(), $app->make(TenantContextResolver::class), $this->handlers($app), $this->dueSources($app), (int) $app['config']->get('corex-wakeup.lease_seconds'), (int) $app['config']->get('corex-wakeup.max_attempts')));

        if ($this->app->runningInConsole()) {
            $this->commands([SweepWakeups::class]);
        }
    }

    /** @return array<string, WakeupHandler> */
    private function handlers(Application $app): array
    {
        $handlers = [];
        foreach ((array) $app['config']->get('corex-wakeup.handlers', []) as $kind => $class) {
            $handler = $app->make($class);

            if (! is_string($kind) || ! $handler instanceof WakeupHandler) {
                throw new RuntimeException('Wakeup handlers must map a kind to WakeupHandler.');
            }
            $handlers[$kind] = $handler;
        }

        return $handlers;
    }

    /** @return list<DueSource> */
    private function dueSources(Application $app): array
    {
        $sources = [];

        foreach ($app->tagged('corex.wakeup.due-sources') as $source) {
            if (! $source instanceof DueSource) {
                throw new RuntimeException('Wakeup due sources must implement DueSource.');
            }

            $sources[] = $source;
        }

        return $sources;
    }

    /** @return list<WakeupHint> */
    private function hints(Application $app): array
    {
        $hints = [];

        foreach ($app->tagged('corex.wakeup.hints') as $hint) {
            if (! $hint instanceof WakeupHint) {
                throw new RuntimeException('Wakeup hints must implement WakeupHint.');
            }

            $hints[] = $hint;
        }

        return $hints;
    }
}
