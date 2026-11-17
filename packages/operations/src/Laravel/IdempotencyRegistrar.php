<?php

declare(strict_types=1);

namespace CoreX\Operations\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Errors\Contracts\ProblemRegistry;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\ReplayAuthorizer;
use CoreX\Operations\Errors\Internal\ErrorMapper;
use CoreX\Operations\Idempotency\Contracts\IdempotencyStore;
use CoreX\Operations\Idempotency\Internal\IdempotentOperationInvoker;
use CoreX\Operations\Idempotency\Internal\SqlIdempotencyStore;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Override;
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
                authorizer: $app->make(OperationAuthorizer::class),
                replayAuthorizer: $app->make(ReplayAuthorizer::class),
                errors: $app->make(ErrorMapper::class),
                problems: $app->make(ProblemRegistry::class),
            ));
        $app->scoped(IdempotencyStore::class, static fn (Application $app): SqlIdempotencyStore => (new IdempotencyStoreFactory)->make($app));
    }

    #[Override]
    public function boot(Application $app): void {}
}
