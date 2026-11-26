<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Internal;

use CoreX\Tenancy\DomainTrust\Data\NormalizedHost;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Runtime\Exceptions\TenantCouldNotBeIdentifiedByHostException;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Database\ConnectionResolverInterface;
use InvalidArgumentException;
use Packman\Attributes\Internal;

/**
 * Resolves `Request::getHost()` to an account id through `root_domains` (verified, not deleted)
 * and `root_accounts` (not deleted, slug set — a pending shell has neither a verified
 * domain nor a slug, so it is unreachable by host). Positive entries are fenced by a
 * shared generation; the explicit host store lives outside the tenant cache stores.
 */
#[Internal]
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

        // A transaction can see uncommitted data or an older snapshot. Neither belongs
        // in a shared cache, even when that transaction will eventually commit.
        if (! $this->options->hostCache
            || $this->connections->connection($this->options->centralConnection)->transactionLevel() > 0) {
            return $this->lookup($host) ?? throw TenantCouldNotBeIdentifiedByHostException::forHost($host);
        }

        $store = $this->cache->store($this->options->hostCacheStore);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $generation = $store->get(self::generationKey());

            if (! is_string($generation) || $generation === '') {
                // A missing/evicted generation must never reuse an old entry's fence.
                $generation = bin2hex(random_bytes(16));

                if (! $store->forever(self::generationKey(), $generation)) {
                    throw TenantCouldNotBeIdentifiedByHostException::forHost($host);
                }
            }

            $entry = $store->get(self::key($host));
            $id = is_array($entry) && ($entry['generation'] ?? null) === $generation
                && is_string($entry['account'] ?? null) ? $entry['account'] : null;

            if ($id === null) {
                $id = $this->lookup($host);

                if ($id !== null) {
                    $store->put(self::key($host), ['generation' => $generation, 'account' => $id], $this->options->hostCacheTtl);
                }
            }

            // A late miss may overwrite an entry after invalidation, but its old fence
            // cannot authorize either this response or any subsequent cache hit.
            if ($store->get(self::generationKey()) === $generation) {
                return $id ?? throw TenantCouldNotBeIdentifiedByHostException::forHost($host);
            }
        }

        throw TenantCouldNotBeIdentifiedByHostException::forHost($host);
    }

    public static function generationKey(): string
    {
        // '@' cannot occur in a normalized host, so this cannot alias a mapping key.
        return self::key('@generation');
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
