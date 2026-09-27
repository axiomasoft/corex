<?php

declare(strict_types=1);

use CoreX\Exceptions\ScopeContextMismatch;
use CoreX\Support\Data\DataScope;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\GuardedTenantContextRestorer;
use CoreX\Tenancy\TenantContext;
use CoreX\Tenancy\WorkspaceRef;

final class MutableRestorerContextResolver implements TenantContextResolver
{
    public function __construct(public TenantContext $context) {}

    public function current(): TenantContext
    {
        return $this->context;
    }
}

function restorerDataScope(TenantContext $context): DataScope
{
    return DataScope::fromTenantContext(
        context: $context,
        connectionIdentity: 'conn-1',
        actorId: 'actor-1',
        restoreEpoch: str_repeat('a', 64),
    );
}

function restorerAccount(string $id): AccountRef
{
    return new AccountRef(id: $id, slug: $id, status: 'active', features: [], limits: []);
}

it('returns the active context when it matches the durable scope', function (): void {
    $context = new TenantContext(account: restorerAccount('account-1'), boxed: true);
    $restorer = new GuardedTenantContextRestorer(new MutableRestorerContextResolver($context));
    $scope = restorerDataScope($context);

    expect($restorer->restore($scope))->toBe($context);
});

it('rejects a mismatched account with a specific message', function (): void {
    $scopeContext = new TenantContext(account: restorerAccount('account-scope'), boxed: true);
    $active = new TenantContext(account: restorerAccount('account-active'), boxed: true);
    $restorer = new GuardedTenantContextRestorer(new MutableRestorerContextResolver($active));

    expect(fn () => $restorer->restore(restorerDataScope($scopeContext)))
        ->toThrow(ScopeContextMismatch::class, 'Data scope account [account-scope] does not match the active context [account-active].');
});

it('rejects a mismatched workspace with a specific message', function (): void {
    $account = restorerAccount('account-1');
    $scopeContext = new TenantContext(
        account: $account,
        workspace: new WorkspaceRef(id: 'ws-scope', slug: 'ws-scope', type: 'team', path: '/ws-scope', parentId: null),
        boxed: true,
    );
    $active = new TenantContext(
        account: $account,
        workspace: new WorkspaceRef(id: 'ws-active', slug: 'ws-active', type: 'team', path: '/ws-active', parentId: null),
        boxed: true,
    );
    $restorer = new GuardedTenantContextRestorer(new MutableRestorerContextResolver($active));

    expect(fn () => $restorer->restore(restorerDataScope($scopeContext)))
        ->toThrow(ScopeContextMismatch::class, 'Data scope workspace [ws-scope] does not match the active context [ws-active].');
});

it('rejects a mismatched boxed profile with a specific message', function (): void {
    $account = restorerAccount('account-1');
    $scopeContext = new TenantContext(account: $account, boxed: true);
    $active = new TenantContext(account: $account, boxed: false);
    $restorer = new GuardedTenantContextRestorer(new MutableRestorerContextResolver($active));

    expect(fn () => $restorer->restore(restorerDataScope($scopeContext)))
        ->toThrow(
            ScopeContextMismatch::class,
            'Data scope was produced by a boxed profile but the active context is not boxed.',
        );
});
