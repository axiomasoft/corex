<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Support;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\Runtime\Internal\HostAccountResolver;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @corex-model-extension Observes saved/deleting of the Account and Domain models; reads the Domain `host` and the Account key.
 *
 * Forgets cached host → account mappings. Wired as `saved`/`deleting` listener of the
 * Account and Domain models and called by lifecycle transitions after commit.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class HostResolverCacheInvalidator
{
    public function __construct(
        private ConnectionResolverInterface $connections,
        private CacheFactory $cache,
        private TenancyOptions $options,
    ) {}

    /** A domain row changed: its current and its previous host stop resolving from cache. */
    public function domainChanged(Model $domain): void
    {
        if (! $this->enabled()) {
            return;
        }

        foreach ([$domain->getAttribute('host'), $domain->getOriginal('host')] as $host) {
            if (is_string($host) && $host !== '') {
                $this->forgetHost($host);
            }
        }
    }

    /** An account row changed: every host of the account (soft-deleted rows included) is forgotten. */
    public function accountChanged(Model $account): void
    {
        $key = $account->getKey();

        if (is_string($key) || is_int($key)) {
            $this->forgetAccount((string) $key);
        }
    }

    public function forgetAccount(string $accountId): void
    {
        if (! $this->enabled()) {
            return;
        }

        $hosts = $this->connections->connection($this->options->centralConnection)
            ->table('root_domains')->where('account_id', $accountId)->pluck('host');

        foreach ($hosts as $host) {
            if (is_string($host)) {
                $this->forgetHost($host);
            }
        }
    }

    private function enabled(): bool
    {
        return $this->options->hostIdentificationEnabled && $this->options->hostCache;
    }

    private function forgetHost(string $host): void
    {
        $this->cache->store($this->options->hostCacheStore)
            ->forget(HostAccountResolver::key(strtolower($host)));
    }
}
