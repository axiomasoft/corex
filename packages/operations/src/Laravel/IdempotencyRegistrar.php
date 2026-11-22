<?php

declare(strict_types=1);

namespace CoreX\Operations\Laravel;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationResourceAuthorizer;
use CoreX\Operations\Authorization\Contracts\ReplayAuthorizer;
use CoreX\Operations\Bulk\Laravel\BulkSubmitGateway;
use CoreX\Operations\Bulk\Laravel\SubmitBulkOperation;
use CoreX\Operations\Cost\Contracts\BudgetExecution;
use CoreX\Operations\Errors\Internal\ErrorMapper;
use CoreX\Operations\Idempotency\Contracts\IdempotencyStore;
use CoreX\Operations\Idempotency\Internal\IdempotentOperationInvoker;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Internal\OperationInput;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use LogicException;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Errors\Contracts\ProblemRegistry;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Modules\Contracts\Registry\ModuleRegistry;

/** Composition of the operations package. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class IdempotencyRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->extend(OperationInvoker::class, static fn (OperationInvoker $inner, Application $app): OperationInvoker => $inner instanceof IdempotentOperationInvoker
            ? $inner
            : new IdempotentOperationInvoker(
                inner: $inner,
                store: static fn (): IdempotencyStore => $app->make(IdempotencyStore::class),
                connection: $app->make(Connection::class),
                modules: $app->make(ModuleRegistry::class),
                scopes: $app->make(DataScopeFactory::class),
                current: $app->make(CurrentWorkScope::class),
                clientScopes: $app->make(ClientScopeAuthorizer::class),
                fields: $app->make(OperationFieldAuthorizer::class),
                authorizer: $app->make(OperationAuthorizer::class),
                replayAuthorizer: $app->make(ReplayAuthorizer::class),
                errors: $app->make(ErrorMapper::class),
                problems: $app->make(ProblemRegistry::class),
                budget: $app->bound(BudgetExecution::class) ? $app->make(BudgetExecution::class) : null,
                bulk: static fn (): SubmitBulkOperation => $app->make(SubmitBulkOperation::class),
                references: $app->make(ReferenceAdmission::class),
                inputs: $app->make(OperationInput::class)->normalize(...),
                bulkGateway: static fn (): BulkSubmitGateway => $app->make(BulkSubmitGateway::class),
                ownerPolicy: static fn (OperationInvocation $invocation): ?OperationResourceAuthorizer => self::ownerPolicy(app: $app, invocation: $invocation),
                resources: $app->bound(OperationResourceAuthorizer::class) ? $app->make(OperationResourceAuthorizer::class) : null,
            ));
        $app->scoped(IdempotencyStore::class, static fn (Application $app): IdempotencyStore => (new IdempotencyStoreFactory)->make($app));
    }

    #[Override]
    public function boot(Application $app): void {}

    private static function ownerPolicy(Application $app, OperationInvocation $invocation): ?OperationResourceAuthorizer
    {
        $handler = $invocation->operation->handler;

        if (! is_a($handler, OperationResourceAuthorizer::class, true)) {
            return null;
        }

        $policy = $app->make($handler);

        return $policy instanceof OperationResourceAuthorizer ? $policy : throw new LogicException('Invalid owner resource policy.');
    }
}
