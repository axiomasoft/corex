<?php

declare(strict_types=1);

namespace CoreX\Scheduler\Laravel;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Scheduler\Internal\SchedulerOptions;
use CoreX\Scheduler\Internal\SqlWakeupScheduler;
use CoreX\Scheduler\Internal\SweepRunner;
use CoreX\Scheduler\Internal\WakeupHandlerRegistry;
use CoreX\Scheduler\Internal\WakeupInput;
use CoreX\Scheduler\Internal\WakeupModuleReferences;
use CoreX\Scheduling\Contracts\DueWakeupSource;
use CoreX\Scheduling\Contracts\DueWakeupSweeper;
use CoreX\Scheduling\Contracts\WakeupScheduleHint;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\WakeupHandlerDefinition;
use CoreX\Time\Laravel\SystemClock;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Config\Exceptions\InvalidConfiguration;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Psr\Clock\ClockInterface;
use RuntimeException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WakeupBindingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->scoped(WakeupModuleReferences::class);
        $app->tag(WakeupModuleReferences::class, ModuleReferences::class);

        $app->singleton(SchedulerOptions::class, static fn (Application $app): SchedulerOptions => SchedulerOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-scheduler', []),
        ));

        if (! $app->bound(ClockInterface::class)) {
            $app->singleton(ClockInterface::class, static fn (): SystemClock => new SystemClock);
        }

        $app->singleton(
            WakeupHandlerRegistry::class,
            static fn (Application $app): WakeupHandlerRegistry => WakeupHandlerRegistry::compile(
                definitions: self::definitions($app),
                registryFingerprint: self::registryFingerprint($app->bound(ModuleRegistry::class) ? $app->make(ModuleRegistry::class) : null),
            ),
        );
        $app->scoped(
            WakeupScheduler::class,
            static fn (Application $app): SqlWakeupScheduler => new SqlWakeupScheduler(
                databases: $app->make(DatabaseManager::class),
                current: $app->make(CurrentWorkScope::class),
                runner: $app->make(WorkScopeRunner::class),
                issuer: $app->make(StoredWorkIssuer::class),
                input: $app->make(WakeupInput::class),
                handlers: $app->make(WakeupHandlerRegistry::class),
                hints: self::hints($app),
                clock: $app->make(ClockInterface::class),
            ),
        );
        $app->scoped(DueWakeupSweeper::class, static fn (Application $app): SweepRunner => new SweepRunner(
            databases: $app->make(DatabaseManager::class),
            contexts: $app->make(TenantContextResolver::class),
            current: $app->make(CurrentWorkScope::class),
            scopes: $app->make(DataScopeFactory::class),
            runner: $app->make(WorkScopeRunner::class),
            stored: $app->make(StoredWorkRunner::class),
            codec: $app->make(DataScopeCodec::class),
            input: $app->make(WakeupInput::class),
            handlers: $app->make(WakeupHandlerRegistry::class),
            resolveHandler: static fn (string $handler) => $app->make($handler),
            dueSources: static fn (): array => self::dueSources($app),
            leaseSeconds: $app->make(SchedulerOptions::class)->leaseSeconds,
            maxAttempts: $app->make(SchedulerOptions::class)->maxAttempts,
            clock: $app->make(ClockInterface::class),
        ));
    }

    #[Override]
    public function boot(Application $app): void
    {
        $app->make(SchedulerOptions::class);
        self::definitions($app);
    }

    /** @return list<WakeupHandlerDefinition> */
    private static function definitions(Application $app): array
    {
        $definitions = [
            ...$app->make(SchedulerOptions::class)->handlers,
            ...$app->tagged(WakeupHandlerDefinition::TAG),
        ];
        $seen = [];

        foreach ($definitions as $definition) {
            if (! $definition instanceof WakeupHandlerDefinition) {
                throw new RuntimeException('Wakeup handler definitions must be WakeupHandlerDefinition.');
            }

            if (isset($seen[$definition->kind])) {
                throw new InvalidConfiguration([
                    'corex-scheduler.handlers.'.$definition->kind.': duplicate wakeup handler kind',
                ]);
            }

            $seen[$definition->kind] = true;
        }

        return array_values($definitions);
    }

    private static function registryFingerprint(?ModuleRegistry $modules): string
    {
        if ($modules === null) {
            return WakeupHandlerRegistry::ModuleFree;
        }

        return $modules->compiled()->fingerprint;
    }

    /** @return list<DueWakeupSource> */
    private static function dueSources(Application $app): array
    {
        $sources = [];

        foreach ($app->tagged(DueWakeupSource::TAG) as $source) {
            if (! $source instanceof DueWakeupSource) {
                throw new RuntimeException('Wakeup due sources must implement DueWakeupSource.');
            }

            $sources[] = $source;
        }

        return $sources;
    }

    /** @return list<WakeupScheduleHint> */
    private static function hints(Application $app): array
    {
        $hints = [];

        foreach ($app->tagged(WakeupScheduleHint::TAG) as $hint) {
            if (! $hint instanceof WakeupScheduleHint) {
                throw new RuntimeException('Wakeup hints must implement WakeupScheduleHint.');
            }

            $hints[] = $hint;
        }

        return $hints;
    }
}
