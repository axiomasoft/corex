<?php

declare(strict_types=1);

namespace CoreX\Modules\Registry;

use Closure;
use CoreX\Modules\Contracts\ModuleRegistryCache;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * {@see ModuleRegistryCache} over any {@see CacheRepository} — deliberately
 * WITHOUT `tags()` (D30/A1/A2), so it works on the default `file`/`database`
 * store and in the boxed profile. Store, TTL and prefix come from
 * `config('corex-modules.cache.*')` (A45/A46 — no hardcoded 3600).
 */
final class RepositoryModuleRegistryCache implements ModuleRegistryCache
{
    public function __construct(
        private readonly CacheRepository $store,
        private readonly int $ttl,
        private readonly string $prefix,
    ) {}

    public function rememberEnabled(string $accountId, int $version, Closure $resolver): array
    {
        // Epoch-stamped key (D39): {prefix}:account:{id}:v{version}:enabled.
        // A bumped version routes to a fresh key — the stale one is left to
        // expire on its own TTL, so no cross-node forget() is needed. NO
        // tags(): the account id already namespaces the key.
        $key = sprintf('%s:account:%s:v%d:enabled', $this->prefix, $accountId, $version);

        return $this->store->remember($key, $this->ttl, $resolver);
    }
}
