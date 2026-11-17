<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Cost\Contracts\BudgetExecution;
use CoreX\Operations\Cost\Contracts\OperationBudget;
use CoreX\Operations\Cost\Contracts\OperationCostEstimator;
use CoreX\Operations\Cost\Data\CostOptions;
use CoreX\Operations\Cost\Internal\BudgetAdmission;
use CoreX\Operations\Cost\Internal\BudgetRetention;
use CoreX\Operations\Cost\Internal\DeclaredCostEstimator;
use CoreX\Operations\Cost\Internal\SqlOperationBudget;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Packman\Foundation\Contracts\PackageRegistrar;
use Psr\Clock\ClockInterface;

/** Composition of the package; the legacy static entry point delegates here. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CostBindingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        // Registration below depends on enabled flags; this one-off read does not cache Options before boot.
        $options = CostOptionsParser::fromArray((array) $app->make(Repository::class)->get('corex-operations.cost', []));

        $app->singleton(CostOptions::class, static fn (Application $app): CostOptions => CostOptionsParser::fromArray(
            (array) $app->make(Repository::class)->get('corex-operations.cost', []),
        ));
        $app->singleton(OperationCostEstimator::class, DeclaredCostEstimator::class);
        $app->scoped(BudgetRetention::class, static fn (Application $app): BudgetRetention => new BudgetRetention(
            connection: $app->make(ApiSchemaConnections::class)->budget(),
            clock: $app->make(ClockInterface::class),
            terminalRetentionSeconds: $app->make(CostOptions::class)->terminalRetentionSeconds,
        ));

        if (! $options->enabled) {
            return;
        }

        $app->scoped(OperationBudget::class, static fn (Application $app): SqlOperationBudget => new SqlOperationBudget(
            connection: $app->make(ApiSchemaConnections::class)->budget(),
            clock: $app->make(ClockInterface::class),
            scopeHmacSecret: $app->make(CostOptions::class)->scopeHmacSecret,
            capacity: $app->make(CostOptions::class)->capacity,
            restoreRate: $app->make(CostOptions::class)->restoreRate,
            enforcedLevels: $app->make(CostOptions::class)->enforcedLevels,
            reservationTtlSeconds: $app->make(CostOptions::class)->reservationTtlSeconds,
        ));
        $app->bind(BudgetExecution::class, static fn (Application $app): BudgetExecution => $app->make(BudgetAdmission::class));
        $app->scoped(BudgetAdmission::class, static fn (Application $app): BudgetAdmission => new BudgetAdmission(
            budget: $app->make(OperationBudget::class),
            estimator: $app->make(OperationCostEstimator::class),
            installationId: $app->make(CostOptions::class)->installationId,
        ));
    }

    #[Override]
    public function boot(Application $app): void {}
}
