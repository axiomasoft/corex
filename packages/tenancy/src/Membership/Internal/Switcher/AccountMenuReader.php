<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Internal\Switcher;

use CoreX\Tenancy\Membership\Contracts\AccountClickAuthorizer;
use CoreX\Tenancy\Membership\Contracts\AccountMenuCache;
use CoreX\Tenancy\Membership\Contracts\AccountsReadTransport;
use CoreX\Tenancy\Membership\Data\AccountsQuery;
use CoreX\Tenancy\Membership\Exceptions\CentralProtocolViolation;
use Packman\Attributes\Internal;
use Throwable;

/** @internal Unregistered local reference; menu output never grants account access. */
#[Internal]
final readonly class AccountMenuReader
{
    public const int CACHE_TTL_SECONDS = 300;

    public function __construct(
        private AccountsReadTransport $transport,
        private AccountMenuCache $cache,
        private AccountClickAuthorizer $clickAuthorizer,
    ) {}

    public function read(AccountsQuery $query): AccountMenu
    {
        try {
            $version = $this->cache->versionFor(query: $query);
        } catch (Throwable) {
            return AccountMenu::unavailable();
        }

        if ($version !== null) {
            $cached = $this->cache->resultFor(query: $query, membershipVersion: $version);

            if ($cached !== null && $cached->membershipVersion === $version && $cached->isFor(query: $query)) {
                return AccountMenu::available(result: $cached);
            }
        }

        try {
            $result = $this->transport->accountsFor(query: $query);

            if (! $result->isFor(query: $query)) {
                throw new CentralProtocolViolation('Accounts response does not match its identity/product query.');
            }

            $this->cache->put(query: $query, result: $result, ttlSeconds: self::CACHE_TTL_SECONDS);

            return AccountMenu::available(result: $result);
        } catch (Throwable) {
            return AccountMenu::unavailable();
        }
    }

    public function authorizeClick(AccountsQuery $query, string $accountId): bool
    {
        return $this->clickAuthorizer->authorize(query: $query, accountId: $accountId);
    }
}
