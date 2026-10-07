<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Internal\Bootstrappers;

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Context\Data\DatabaseConnectionConfig;
use CoreX\Context\Data\TenantContext;
use CoreX\Tenancy\Runtime\Contracts\TenantBootstrapper;
use CoreX\Tenancy\Runtime\Internal\FrameworkConfig;
use Illuminate\Cache\CacheManager;
use Override;

/**
 * Isolates the configured cache stores per account: key prefix for redis/memcached/database
 * stores, a per-account directory (and lock directory) for the file store. The whole store
 * config is copied before the first mutation and restored byte-for-byte on revert.
 */
final class CachePrefixBootstrapper implements TenantBootstrapper
{
    public const array SUPPORTED_DRIVERS = ['redis', 'memcached', 'database', 'file'];

    /** @var array<string, array<array-key, mixed>> */
    private array $original = [];

    /** @param list<string> $stores */
    public function __construct(
        private readonly CacheManager $cache,
        private readonly FrameworkConfig $config,
        private readonly array $stores,
        private readonly string $prefix,
        private readonly string $centralConnection,
    ) {}

    #[Override]
    public function bootstrap(TenantContext $context, DatabaseConnectionConfig $database): void
    {
        $suffix = hash('sha256', $context->account->id);

        foreach ($this->stores as $store) {
            $key = "cache.stores.{$store}";
            $base = (array) $this->config->get($key);
            $this->original[$store] = $base; // before any mutation, including the throwing one

            $driver = $base['driver'] ?? null;

            if (! in_array($driver, self::SUPPORTED_DRIVERS, true)) {
                throw new InvalidConfiguration([
                    "corex-tenancy.cache_stores.{$store}: unsupported tenant cache driver",
                ]);
            }

            if ($driver === 'file') {
                $path = $base['path'] ?? null;
                $lockPath = $base['lock_path'] ?? $path;

                if (! is_string($path) || ! is_string($lockPath)) {
                    throw new InvalidConfiguration(["corex-tenancy.cache_stores.{$store}: file store needs a path"]);
                }

                $base['path'] = rtrim($path, '/').'/corex-tenants/'.$suffix;
                $base['lock_path'] = rtrim($lockPath, '/').'/corex-tenants/'.$suffix;
            } else {
                $existing = $base['prefix'] ?? $this->config->get(key: 'cache.prefix', default: '');
                $base['prefix'] = str_replace('%tenant%', $suffix, $this->prefix).(is_string($existing) ? $existing : '');
            }

            if ($driver === 'database') {
                // The tenant default connection must not relocate the cache tables.
                $base['connection'] ??= $this->centralConnection;
                $base['lock_connection'] ??= $base['connection'];
            }

            $this->config->set(key: $key, value: $base);
            $this->cache->forgetDriver($store);
        }
    }

    #[Override]
    public function revert(): void
    {
        foreach ($this->original as $store => $base) {
            $this->config->set(key: "cache.stores.{$store}", value: $base);
            $this->cache->forgetDriver($store);
        }

        $this->original = [];
    }
}
