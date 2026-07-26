<?php

declare(strict_types=1);

namespace CoreX\Auth\Matrix;

use CoreX\Tenancy\Contracts\TenantContextResolver;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;

/**
 * Versioned cache key for one role's compiled scope-map (B-11 §5.5) — bumped
 * as the LAST step of {@see DatabaseRoleMatrixCompiler::compile()} (§7.3
 * п.8), never before the transaction that wrote the matching AzGuard keys
 * commits. Carries an explicit tenant salt (the account id) rather than
 * relying on a cache-store prefix bootstrapper (findings/P2.8-azguard-
 * inventory-2026-07-26.md §5): `corex/auth` runs in boxed installs too,
 * where no such bootstrapper is wired.
 */
final readonly class ScopeMapVersion
{
    public function __construct(
        private TenantContextResolver $tenantContext,
        private CacheFactory $cache,
    ) {}

    public function current(string $roleId): int
    {
        return (int) $this->store()->get($this->key($roleId), 0);
    }

    /** Bumps and returns the new version. */
    public function bump(string $roleId): int
    {
        $store = $this->store();
        $version = $this->current($roleId) + 1;
        $store->forever($this->key($roleId), $version);

        return $version;
    }

    private function key(string $roleId): string
    {
        $accountId = $this->tenantContext->current()->account->id;

        return "corex-auth.scope-map.{$accountId}.{$roleId}";
    }

    private function store(): Repository
    {
        /** @var ?string $storeName */
        $storeName = config('corex-auth.cache.store');

        return $this->cache->store($storeName);
    }
}
