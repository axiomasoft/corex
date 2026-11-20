<?php

declare(strict_types=1);

namespace CoreX\Operations\Laravel;

use Closure;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Cost\Contracts\OperationBudget;
use CoreX\Operations\Cost\Contracts\OperationCostEstimator;
use CoreX\Operations\Cost\Data\CostInput;
use CoreX\Operations\Cost\Internal\BudgetAdmission;
use CoreX\Operations\Cost\Internal\DeclaredCostEstimator;
use CoreX\Operations\Cost\Internal\SqlOperationBudget;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Internal\DefaultOperationInvoker;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Connection;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Config\Data\SecretRef;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Psr\Clock\ClockInterface;

/** Test composition using the engine's production invocation and budget implementations. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class InvocationFixtures
{
    public static function declaredCosts(): OperationCostEstimator
    {
        return new DeclaredCostEstimator;
    }

    public static function sqlBudget(Connection $connection, ClockInterface $clock, SecretRef $scopeHmacSecret, int $capacity = 100, int $restoreRate = 10): OperationBudget
    {
        return new SqlOperationBudget(connection: $connection, clock: $clock, scopeHmacSecret: $scopeHmacSecret, capacity: $capacity, restoreRate: $restoreRate);
    }

    /**
     * @param  Closure(OperationInvocation):CostInput  $costInput
     * @param  Closure(OperationInvocation):?string  $clientId
     */
    public static function costInvoker(ModuleRegistry $modules, ClientScopeAuthorizer $clientScopes, OperationAuthorizer $authorizer, OperationFieldAuthorizer $fields, Container $container, OperationBudget $budget, string $installationId, Closure $costInput, Closure $clientId): OperationInvoker
    {
        return new DefaultOperationInvoker(modules: $modules, clientScopes: $clientScopes, authorizer: $authorizer, fields: $fields, container: $container, budget: new BudgetAdmission(budget: $budget, estimator: self::declaredCosts(), installationId: $installationId, exceptions: $container->make(ExceptionHandler::class), costInput: $costInput, clientId: $clientId));
    }
}
