<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Internal;

use CoreX\Tenancy\DomainTrust\Data\NormalizedHost;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Runtime\Exceptions\TenantCouldNotBeIdentifiedByHostException;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Database\ConnectionResolverInterface;
use InvalidArgumentException;

/**
 * Resolves `Request::getHost()` to an account id through `root_domains` (verified, not deleted)
 * and `root_accounts` (not deleted, slug set — §7.3 п.9: a pending shell has neither a verified
 * domain nor a slug, so it is unreachable by host). The cache holds the account id only and
 * never a miss; it lives in the explicit host store, outside the tenant cache stores.
 */
final readonly class HostAccountResolver
{
    public function __construct(
        private ConnectionResolverInterface $connections,
        private CacheFactory $cache,
        private TenancyOptions $options,
    ) {}

    /** @return string account id */
    public function resolve(string $host): string
    {
        $host = self::normalizeHost($host);
        $store = $this->cache->store($this->options->hostCacheStore);
        $id = $this->options->hostCache ? $store->get(self::key($host)) : null;

        if (! is_string($id)) {
            $id = $this->lookup($host);

            if ($id !== null && $this->options->hostCache) {
                $store->put(self::key($host), $id, $this->options->hostCacheTtl);
            }
        }

        return $id ?? throw TenantCouldNotBeIdentifiedByHostException::forHost($host);
    }

    public static function key(string $host): string
    {
        return 'corex-tenancy:host:'.$host;
    }

    public static function normalizeHost(string $host): string
    {
        try {
            return (new NormalizedHost($host))->value;
        } catch (InvalidArgumentException) {
            throw TenantCouldNotBeIdentifiedByHostException::forHost($host);
        }
    }

    private function lookup(string $host): ?string
    {
        $id = $this->connections->connection($this->options->centralConnection)
            ->table('root_domains as d')
            ->join('root_accounts as a', 'a.id', '=', 'd.account_id')
            ->whereRaw('lower(d.host) = ?', [$host])
            ->whereNull('d.deleted_at')
            ->whereNotNull('d.verified_at')
            ->whereNull('a.deleted_at')
            ->whereNotNull('a.slug')
            ->value('d.account_id');

        return is_string($id) ? $id : null;
    }
}
