<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Internal\AllowAllDataScopeAuthorizer;
use Illuminate\Contracts\Container\Container;

final class WorkUnitMutableContextResolver implements TenantContextResolver
{
    public function __construct(public TenantContext $context) {}

    public function current(): TenantContext
    {
        return $this->context;
    }
}

function workUnitAccount(string $id): AccountRef
{
    return new AccountRef(id: $id, slug: $id, status: 'active', features: [], limits: []);
}

function workUnitScope(TenantContext $context): DataScope
{
    return DataScope::fromTenantContext(
        context: $context,
        connectionIdentity: 'sqlite::memory:'.$context->account->id,
        actorId: 'actor-'.$context->account->id,
        restoreEpoch: str_repeat('a', 64),
    );
}

/**
 * @return array{0: DataScope, 1: object, 2: DataScopeAuthorizer|null}
 */
function workUnitPassA(Container $container, TenantContext $contextA): array
{
    $container->instance(
        TenantContextResolver::class,
        new WorkUnitMutableContextResolver($contextA),
    );

    $originalAuthorizer = $container->bound(DataScopeAuthorizer::class)
        ? $container->make(DataScopeAuthorizer::class)
        : null;

    $previousAuthorizer = $container->bound(DataScopeAuthorizer::class)
        ? $container->make(DataScopeAuthorizer::class)
        : null;

    $container->instance(DataScopeAuthorizer::class, new AllowAllDataScopeAuthorizer);
    $container->forgetScopedInstances();

    $scopeA = workUnitScope($contextA);
    $scopedFromA = $container->make(TenantContextRestorer::class);

    try {
        throw new RuntimeException('work-unit-pass-a');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('work-unit-pass-a');
    } finally {
        if ($previousAuthorizer instanceof DataScopeAuthorizer) {
            $container->instance(DataScopeAuthorizer::class, $previousAuthorizer);
        } else {
            $container->forgetInstance(DataScopeAuthorizer::class);
        }

        $container->forgetScopedInstances();
    }

    return [$scopeA, $scopedFromA, $originalAuthorizer];
}

function workUnitInspectPassB(
    Container $container,
    TenantContext $contextB,
    DataScope $scopeA,
    object $scopedFromA,
    ?DataScopeAuthorizer $originalAuthorizer,
    array $configBaseline,
): object {
    $container->instance(
        TenantContextResolver::class,
        new WorkUnitMutableContextResolver($contextB),
    );

    expect(fn () => $container->make(TenantContextRestorer::class)->restore($scopeA))
        ->toThrow(ScopeContextMismatch::class);

    expect($container['config']->get('corex.work_unit_isolation.leak_marker'))->toBeNull()
        ->and($container['config']->get('database.connections.testbench'))->toBe($configBaseline);

    if ($originalAuthorizer instanceof DataScopeAuthorizer) {
        expect($container->make(DataScopeAuthorizer::class))->toBe($originalAuthorizer);
    } else {
        expect($container->bound(DataScopeAuthorizer::class))->toBeFalse();
    }

    $scopedBeforeBInit = $container->make(TenantContextRestorer::class);
    expect($scopedBeforeBInit)->not->toBe($scopedFromA);

    return $scopedBeforeBInit;
}

it('isolates consecutive work units when the first pass throws', function (): void {
    $container = app();
    $configBaseline = $container['config']->get('database.connections.testbench');
    $contextA = new TenantContext(account: workUnitAccount('account-a'), boxed: true);
    $contextB = new TenantContext(account: workUnitAccount('account-b'), boxed: true);

    [$scopeA, $scopedFromA, $originalAuthorizer] = workUnitPassA($container, $contextA);

    if ($originalAuthorizer instanceof DataScopeAuthorizer) {
        expect($container->make(DataScopeAuthorizer::class))->toBe($originalAuthorizer);
    } else {
        expect($container->bound(DataScopeAuthorizer::class))->toBeFalse();
    }

    $container->forgetScopedInstances();

    $scopedBeforeWarmup = workUnitInspectPassB(
        container: $container,
        contextB: $contextB,
        scopeA: $scopeA,
        scopedFromA: $scopedFromA,
        originalAuthorizer: $originalAuthorizer,
        configBaseline: $configBaseline,
    );

    $container->instance(DataScopeAuthorizer::class, new AllowAllDataScopeAuthorizer);
    $container->forgetScopedInstances();

    $scopedFromB = $container->make(TenantContextRestorer::class);
    expect($scopedFromB)->not->toBe($scopedFromA)
        ->and($scopedFromB)->not->toBe($scopedBeforeWarmup);
});
