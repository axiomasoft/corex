<?php

declare(strict_types=1);

namespace CoreX\Access\Matrix\Internal;

use Closure;
use CoreX\Access\Laravel\AuthOptions;
use CoreX\Access\Matrix\Contracts\ScopeMapVersions;
use CoreX\Context\Contracts\TenantContextResolver;
use Illuminate\Cache\Lock;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;

/**
 * Versioned cache key for one role's compiled scope-map (B-11 §5.5) — bumped
 * as the LAST step of {@see DatabaseRoleMatrixCompiler::compile()} (§7.3
 * п.8), never before the transaction that wrote the matching AzGuard keys
 * commits. Carries an explicit tenant salt (the account id) rather than
 * relying on a cache-store prefix bootstrapper (findings/P2.8-azguard-
 * inventory-2026-07-26.md §5): `axiomasoft/corex-access` runs in boxed installs too,
 * where no such bootstrapper is wired.
 */
final readonly class ScopeMapVersion implements ScopeMapVersions
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
            // The framework polls a blocked lock every 250ms, which is longer than
            // this critical section, so concurrent bumps time out while the lock is free.
            if (! $backend instanceof LockProvider) {
                return $increment();
            }

            $lock = $backend->lock($key.':bump', 10);

            if ($lock instanceof Lock) {
                $lock->betweenBlockedAttemptsSleepFor(50);
            }

            return $lock->block(5, $increment);
        };
    }

    private function key(string $roleId): string
    {
        $accountId = $this->tenantContext->current()->account->id;

        return "corex-access.scope-map.{$accountId}.{$roleId}";
    }

    private function store(): Repository
    {
        return $this->cache->store($this->options->cacheStore);
    }
}
