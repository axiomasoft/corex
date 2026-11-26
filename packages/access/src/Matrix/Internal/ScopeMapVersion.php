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
use Packman\Attributes\Internal;
use UnexpectedValueException;

/**
 * Versioned cache key for one role's compiled scope-map — bumped
 * as the LAST step of {@see DatabaseRoleMatrixCompiler::compile()}, never before the transaction that published the matrix event
 * commits. Carries an explicit tenant salt (the account id) rather than
 * relying on a cache-store prefix bootstrapper: `axiomasoft/corex-access` runs in boxed installs too,
 * where no such bootstrapper is wired.
 */
#[Internal]
final readonly class ScopeMapVersion implements ScopeMapVersions
{
    public function __construct(
        private TenantContextResolver $tenantContext,
        private CacheFactory $cache,
        private AuthOptions $options,
    ) {}

    public function current(string $roleId): int
    {
        $value = $this->store()->get($this->key($roleId), 0);

        if (is_int($value) && $value >= 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[0-9]+$/D', $value) === 1) {
            $version = (int) $value;

            if ((string) $version === $value) {
                return $version;
            }
        }

        throw new UnexpectedValueException('Scope map cache versions must be non-negative integers.');
    }

    /** Bumps and returns the new version. */
    public function bump(string $roleId): int
    {
        return ($this->invalidator($roleId))();
    }

    /**
     * Capture the tenant key before an outer transaction can switch context.
     *
     * @return Closure(): int
     */
    public function invalidator(string $roleId): Closure
    {
        $store = $this->store();
        $key = $this->key($roleId);

        return static function () use ($store, $key): int {
            $increment = static function () use ($store, $key): int {
                $store->add($key, 0);

                $version = $store->increment($key);

                if (! is_int($version) || $version < 1) {
                    throw new UnexpectedValueException('The scope map cache failed to increment its version.');
                }

                return $version;
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

            $version = $lock->block(5, $increment);

            if (! is_int($version)) {
                throw new UnexpectedValueException('The scope map cache lock did not return the incremented version.');
            }

            return $version;
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
