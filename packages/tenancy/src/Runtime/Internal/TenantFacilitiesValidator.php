<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Internal;

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\CachePrefixBootstrapper;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\SessionConnectionBootstrapper;

/**
 * Refuses an unsafe cache/session facility configuration before the first context switch,
 * instead of discovering it half-way through a bootstrap: unsupported drivers, a host or session
 * store that is also a per-account store, a session store of the wrong driver.
 */
final readonly class TenantFacilitiesValidator
{
    public function __construct(
        private FrameworkConfig $config,
        private TenancyOptions $options,
    ) {}

    public function assertValid(): void
    {
        $violations = [];

        if (in_array(CachePrefixBootstrapper::class, $this->options->bootstrappers, true)) {
            $this->cache($violations);
        }

        if (in_array(SessionConnectionBootstrapper::class, $this->options->bootstrappers, true)) {
            $this->session($violations);
        }

        if ($violations !== []) {
            throw new InvalidConfiguration($violations);
        }
    }

    /** @param list<string> $violations */
    private function cache(array &$violations): void
    {
        foreach ($this->options->cacheStores as $store) {
            $path = "corex-tenancy.cache_stores.{$store}";
            $config = $this->config->get("cache.stores.{$store}");

            if (! is_array($config)) {
                $violations[] = "{$path}: store is not configured";

                continue;
            }

            $driver = $config['driver'] ?? null;

            if (! in_array($driver, CachePrefixBootstrapper::SUPPORTED_DRIVERS, true)) {
                $violations[] = "{$path}: unsupported tenant cache driver";

                continue;
            }

            if ($driver === 'file' && ! self::absolute($config['path'] ?? null)) {
                $violations[] = "{$path}: file store needs an absolute path";
            }

            if ($driver === 'file' && isset($config['lock_path']) && ! self::absolute($config['lock_path'])) {
                $violations[] = "{$path}: file store lock_path must be absolute";
            }

            if ($driver === 'redis' && ! self::filled($config['connection'] ?? null)) {
                $violations[] = "{$path}: redis store needs an explicitly named connection";
            }

            if ($driver === 'memcached' && (! is_array($config['servers'] ?? null) || $config['servers'] === [])) {
                $violations[] = "{$path}: memcached store needs explicit servers";
            }
        }

        $host = $this->hostStore();

        if ($host !== null && in_array($host, $this->options->cacheStores, true)) {
            $violations[] = 'corex-tenancy.host_identification.cache_store: set a store outside cache_stores (the host store must not be a per-account store)';
        }
    }

    /** @param list<string> $violations */
    private function session(array &$violations): void
    {
        $driver = $this->config->get('session.driver');

        if ($driver === null) {
            return;
        }

        if (! is_string($driver) || ! in_array($driver, SessionConnectionBootstrapper::SUPPORTED_DRIVERS, true)) {
            $violations[] = 'corex-tenancy.session: unsupported session driver, use database, redis, memcached or file';

            return;
        }

        if ($driver === 'file' && ! self::absolute($this->config->get('session.files'))) {
            $violations[] = 'corex-tenancy.session.files: file sessions need an absolute directory';
        }

        if ($driver !== 'redis' && $driver !== 'memcached') {
            return;
        }

        $name = $this->config->get('session.store');
        $store = is_string($name) ? $this->config->get("cache.stores.{$name}") : null;

        if (! is_string($name) || $name === '' || ! is_array($store)) {
            $violations[] = 'corex-tenancy.session.store: a dedicated configured cache store is required';

            return;
        }

        if (($store['driver'] ?? null) !== $driver) {
            $violations[] = "corex-tenancy.session.store: store [{$name}] must use the {$driver} driver";
        }

        if (in_array($name, $this->options->cacheStores, true) || $name === $this->hostStore()) {
            $violations[] = "corex-tenancy.session.store: store [{$name}] must be separate from the tenant cache and host stores";
        }

        if ($driver === 'redis' && ! self::filled($this->config->get('session.connection'))) {
            $violations[] = 'corex-tenancy.session.connection: a named redis connection is required';
        }
    }

    /** The store that keeps host → account entries; null while host identification is off or uncached. */
    private function hostStore(): ?string
    {
        if (! $this->options->hostIdentificationEnabled || ! $this->options->hostCache) {
            return null;
        }

        $store = $this->options->hostCacheStore ?? $this->config->get('cache.default');

        return is_string($store) && $store !== '' ? $store : null;
    }

    private static function absolute(mixed $path): bool
    {
        return is_string($path) && $path !== '' && str_starts_with($path, '/');
    }

    private static function filled(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
