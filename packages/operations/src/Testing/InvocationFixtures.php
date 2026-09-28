<?php

declare(strict_types=1);

namespace CoreX\Api\Testing;

use Closure;
use CoreX\Api\Contracts\ClientScopeAuthorizer;
use CoreX\Api\Contracts\OperationAuthorizer;
use CoreX\Api\Contracts\OperationBudget;
use CoreX\Api\Contracts\OperationCostEstimator;
use CoreX\Api\Contracts\OperationFieldAuthorizer;
use CoreX\Api\Contracts\OperationInvoker;
use CoreX\Api\Data\CostInput;
use CoreX\Api\Data\OperationInvocation;
use CoreX\Api\Internal\Cost\BudgetAdmission;
use CoreX\Api\Internal\Cost\DeclaredCostEstimator;
use CoreX\Api\Internal\Cost\SqlOperationBudget;
use CoreX\Api\Internal\DefaultOperationInvoker;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Support\SecretRef;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use Psr\Clock\ClockInterface;

/** Test composition using the engine's production invocation and budget implementations. */
final class InvocationFixtures
{
    public static function declaredCosts(): OperationCostEstimator
    {
        return new DeclaredCostEstimator;
    }

    public static function sqlBudget(Connection $connection, ClockInterface $clock, SecretRef $scopeHmacSecret, int $capacity = 100, int $restoreRate = 10): OperationBudget
    {
        return new SqlOperationBudget($connection, $clock, $scopeHmacSecret, capacity: $capacity, restoreRate: $restoreRate);
    }

    /**
     * @param  Closure(OperationInvocation): CostInput  $costInput
     * @param  Closure(OperationInvocation): ?string  $clientId
     */
    public static function costInvoker(ModuleRegistry $modules, ClientScopeAuthorizer $clientScopes, OperationAuthorizer $authorizer, OperationFieldAuthorizer $fields, Container $container, OperationBudget $budget, string $installationId, Closure $costInput, Closure $clientId): OperationInvoker
    {
        return new DefaultOperationInvoker($modules, $clientScopes, $authorizer, $fields, $container, new BudgetAdmission($budget, self::declaredCosts(), $installationId, $costInput, $clientId));
    }
}
