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
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use Illuminate\Contracts\Foundation\Application;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class CostRegistrar
{
    public static function register(Application $app): void
    {
        $config = $app['config']->get('corex-operations.cost');
        $options = CostOptionsParser::fromArray(is_array($config) ? $config : []);
        $app->instance(CostOptions::class, $options);
        $app->singleton(OperationCostEstimator::class, DeclaredCostEstimator::class);
        $app->singleton(BudgetRetention::class, static fn (Application $app): BudgetRetention => new BudgetRetention(
            connection: $app->make(ApiSchemaRegistrar::class)->budget(),
            clock: $app->make(ClockInterface::class),
            terminalRetentionSeconds: $options->terminalRetentionSeconds,
        ));

        if (! $options->enabled) {
            return;
        }

        $app->singleton(OperationBudget::class, static function (Application $app) use ($options): SqlOperationBudget {
            return new SqlOperationBudget(
                connection: $app->make(ApiSchemaRegistrar::class)->budget(),
                clock: $app->make(ClockInterface::class),
                scopeHmacSecret: $options->scopeHmacSecret,
                capacity: $options->capacity,
                restoreRate: $options->restoreRate,
                enforcedLevels: $options->enforcedLevels,
                reservationTtlSeconds: $options->reservationTtlSeconds,
            );
        });
        $app->bind(BudgetExecution::class, static fn (Application $app): BudgetExecution => $app->make(BudgetAdmission::class));
        $app->singleton(BudgetAdmission::class, static fn (Application $app): BudgetAdmission => new BudgetAdmission(
            budget: $app->make(OperationBudget::class),
            estimator: $app->make(OperationCostEstimator::class),
            installationId: $options->installationId,
        ));
    }
}
