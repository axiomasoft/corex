<?php

declare(strict_types=1);

namespace CoreX\Auth\Matrix;

use Closure;
use CoreX\Auth\Support\AuthOptions;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;

/**
 * Versioned cache key for one role's compiled scope-map (B-11 §5.5) — bumped
 * as the LAST step of {@see DatabaseRoleMatrixCompiler::compile()} (§7.3
 * п.8), never before the transaction that wrote the matching AzGuard keys
 * commits. Carries an explicit tenant salt (the account id) rather than
 * relying on a cache-store prefix bootstrapper (findings/P2.8-azguard-
 * inventory-2026-07-26.md §5): `corex/access` runs in boxed installs too,
 * where no such bootstrapper is wired.
 */
final readonly class ScopeMapVersion
{
    public function __construct(
        private TenantContextResolver $tenantContext,
        private CacheFactory $cache,
        private AuthOptions $options,
    ) {}

    public function current(string $roleId): int
    {
        return (int) $this->store()->get($this->key($roleId), 0);
    }

    /** Bumps and returns the new version. */
    public function bump(string $roleId): int
    {
        return ($this->invalidator($roleId))();
    }

    /** Capture the tenant key before an outer transaction can switch context. */
    public function invalidator(string $roleId): Closure
    {
        $store = $this->store();
        $key = $this->key($roleId);

        return static function () use ($store, $key): int {
            $increment = static function () use ($store, $key): int {
                $store->add($key, 0);

                return (int) $store->increment($key);
            };
            $backend = $store->getStore();

            // FileStore's increment is a read/put pair, not an atomic counter.
            return $backend instanceof LockProvider
                ? $backend->lock($key.':bump', 10)->block(5, $increment)
                : $increment();
        };
    }

    private function key(string $roleId): string
    {
        $accountId = $this->tenantContext->current()->account->id;

        return "corex-auth.scope-map.{$accountId}.{$roleId}";
    }

    private function store(): Repository
    {
        return $this->cache->store($this->options->cacheStore);
    }
}
