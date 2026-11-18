<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Support;

use CoreX\Tenancy\Runtime\Internal\HostAccountResolver;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use RuntimeException;

/**
 * @corex-model-extension Observes saved/deleted of the Account and Domain models; reads the Domain `host` and the Account key.
 *
 * Fences cached host → account mappings after commit. The shared generation also
 * covers account deletion cascades, when no domain rows remain to enumerate.
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

        // Capture before Eloquent syncs the original attributes after saved.
        $hosts = [$domain->getAttribute('host'), $domain->getOriginal('host')];

        // Prove cache invalidation is writable while a save can still roll back.
        // The commit fence below must advance again after concurrent refills.
        if ($domain->getConnection()->transactionLevel() > 0) {
            $this->invalidate();
        }

        $domain->getConnection()->afterCommit(function () use ($hosts): void {
            $this->invalidate($hosts);
        });
    }

    /** Account changes fence all mappings, including hosts removed by a delete cascade. */
    public function accountChanged(Model $account): void
    {
        if ($this->enabled()) {
            if ($account->getConnection()->transactionLevel() > 0) {
                $this->invalidate();
            }

            $account->getConnection()->afterCommit(function (): void {
                $this->invalidate();
            });
        }
    }

    public function forgetAccount(string $accountId): void
    {
        if (! $this->enabled()) {
            return;
        }

        $connection = $this->connections->connection($this->options->centralConnection);

        if (! $connection instanceof Connection) {
            throw new RuntimeException('Host cache invalidation requires a Laravel database connection.');
        }

        if ($connection->transactionLevel() > 0) {
            $this->invalidate();
        }

        $connection->afterCommit(function () use ($connection, $accountId): void {
            $hosts = $connection->table('root_domains')->where('account_id', $accountId)->pluck('host');
            $this->invalidate($hosts);
        });
    }

    private function enabled(): bool
    {
        return $this->options->hostIdentificationEnabled && $this->options->hostCache;
    }

    /** @param iterable<mixed> $hosts */
    private function invalidate(iterable $hosts = []): void
    {
        $store = $this->cache->store($this->options->hostCacheStore);

        if (! $store->forever(HostAccountResolver::generationKey(), bin2hex(random_bytes(16)))) {
            throw new RuntimeException('Unable to invalidate the host resolver cache generation.');
        }

        foreach ($hosts as $host) {
            if (is_string($host) && $host !== '') {
                $store->forget(HostAccountResolver::key(strtolower($host)));
            }
        }
    }
}
