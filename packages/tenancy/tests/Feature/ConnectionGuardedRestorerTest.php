<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Internal\GuardedTenantContextRestorer;
use CoreX\Data\Data\DataScope;
use CoreX\Tenancy\Runtime\Internal\ConnectionGuardedTenantContextRestorer;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;

function restorerEpoch(): string
{
    return hash('sha256', 'p9.2-restorer');
}

function restorerAccount(string $id): AccountRef
{
    return new AccountRef(id: $id, slug: 'account', status: 'active', features: [], limits: []);
}

function restorerScope(TenantContext $context, string $connectionIdentity): DataScope
{
    return DataScope::fromTenantContext(
        context: $context,
        connectionIdentity: $connectionIdentity,
        actorId: 'actor-1',
        restoreEpoch: restorerEpoch(),
    );
}

/**
 * @param  callable():TenantContext  $current
 */
function guardedRestorer(callable $current, ?DatabaseManager $database = null): ConnectionGuardedTenantContextRestorer
{
    $resolver = new class($current) implements TenantContextResolver
    {
        public function __construct(private $current) {}

        public function current(): TenantContext
        {
            return ($this->current)();
        }
    };

    return new ConnectionGuardedTenantContextRestorer(
        semantic: new GuardedTenantContextRestorer($resolver),
        database: $database ?? app(DatabaseManager::class),
    );
}

it('rejects a mismatched account before reading the connection', function (): void {
    $active = new TenantContext(restorerAccount('account-a'), new WorkspaceRef('ws-1', 'ws', 'company', 'ws-1', null));
    $foreign = new TenantContext(restorerAccount('account-missing'), $active->workspace);
    $database = new class(app(), app(ConnectionFactory::class)) extends DatabaseManager
    {
        public int $reads = 0;

        public function getDefaultConnection()
        {
            $this->reads++;

            return parent::getDefaultConnection();
        }
    };
    $restorer = guardedRestorer(static fn (): TenantContext => $active, $database);

    expect(fn () => $restorer->restore(restorerScope($foreign, 'pgsql:corex_test')))
        ->toThrow(ScopeContextMismatch::class, 'account-missing')
        ->and($database->reads)->toBe(0);
});

it('rejects workspace, boxed profile, and database mismatches', function (): void {
    $workspace = new WorkspaceRef('ws-1', 'ws', 'company', 'ws-1', null);
    $active = new TenantContext(restorerAccount('account-a'), $workspace);
    $restorer = guardedRestorer(static fn (): TenantContext => $active);

    $otherWorkspace = new TenantContext($active->account, new WorkspaceRef('ws-2', 'other', 'company', 'ws-2', null));
    expect(fn () => $restorer->restore(restorerScope($otherWorkspace, 'pgsql:corex_test')))
        ->toThrow(ScopeContextMismatch::class, 'ws-2');

    $boxedScope = new TenantContext($active->account, $workspace, boxed: true);
    expect(fn () => $restorer->restore(restorerScope($boxedScope, 'boxed:account-a')))
        ->toThrow(ScopeContextMismatch::class, 'boxed');

    expect(fn () => $restorer->restore(restorerScope($active, 'pgsql:other-database')))
        ->toThrow(ScopeContextMismatch::class, 'other-database');
});

it('accepts the active cloud connection and a boxed identity without a registry lookup', function (): void {
    $workspace = new WorkspaceRef('ws-1', 'ws', 'company', 'ws-1', null);
    $cloud = new TenantContext(restorerAccount('account-a'), $workspace);
    $identity = DB::connection()->getDriverName().':'.DB::connection()->getDatabaseName();
    $restorer = guardedRestorer(static fn (): TenantContext => $cloud);

    expect($restorer->restore(restorerScope($cloud, $identity))->account->id)->toBe('account-a');

    $boxed = new TenantContext(restorerAccount('account-boxed'), $workspace, boxed: true);
    $boxedRestorer = guardedRestorer(static fn (): TenantContext => $boxed);

    expect($boxedRestorer->restore(restorerScope($boxed, 'boxed:account-boxed'))->isBoxed())->toBeTrue();
});
