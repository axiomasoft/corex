<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support\Membership;

use CoreX\Tenancy\Membership\Switcher\AccountClickAuthorizer;
use CoreX\Tenancy\Membership\Switcher\AccountMenuCache;
use CoreX\Tenancy\Membership\Switcher\AccountMenuReader;
use CoreX\Tenancy\Membership\Switcher\AccountsQuery;
use CoreX\Tenancy\Membership\Switcher\AccountsReadTransport;
use CoreX\Tenancy\Membership\Switcher\AvailableAccounts;
use CoreX\Tenancy\Membership\Transport\CentralUnavailable;
use Throwable;

/** Disposable reference cache/transport; no Redis, endpoint, or runtime binding. */
final class AccountMenuFixture implements AccountClickAuthorizer, AccountMenuCache, AccountsReadTransport
{
    /** @var array<string, string> */
    private array $versions = [];

    /** @var array<string, array<string, AvailableAccounts>> */
    private array $results = [];

    /** @var list<AvailableAccounts|Throwable> */
    private array $responses = [];

    public int $transportCalls = 0;

    public int $cacheLookups = 0;

    public int $lastTtl = 0;

    public int $authorizationCalls = 0;

    public bool $authorizationResult = false;

    public function reader(): AccountMenuReader
    {
        return new AccountMenuReader(transport: $this, cache: $this, clickAuthorizer: $this);
    }

    public function enqueue(AvailableAccounts|Throwable $response): void
    {
        $this->responses[] = $response;
    }

    public function loseVersionOnly(AccountsQuery $query): void
    {
        unset($this->versions[$this->key(query: $query)]);
    }

    public function accountsFor(AccountsQuery $query): AvailableAccounts
    {
        $this->transportCalls++;
        $response = array_shift($this->responses);

        if ($response instanceof Throwable) {
            throw $response;
        }

        if (! $response instanceof AvailableAccounts) {
            throw new CentralUnavailable('No local authoritative response is queued.');
        }

        return $response;
    }

    public function versionFor(AccountsQuery $query): ?string
    {
        return $this->versions[$this->key(query: $query)] ?? null;
    }

    public function resultFor(AccountsQuery $query, string $membershipVersion): ?AvailableAccounts
    {
        $this->cacheLookups++;

        return $this->results[$this->key(query: $query)][$membershipVersion] ?? null;
    }

    public function put(AccountsQuery $query, AvailableAccounts $result, int $ttlSeconds): void
    {
        $key = $this->key(query: $query);
        $this->versions[$key] = $result->membershipVersion;
        $this->results[$key][$result->membershipVersion] = $result;
        $this->lastTtl = $ttlSeconds;
    }

    public function authorize(AccountsQuery $query, string $accountId): bool
    {
        $this->authorizationCalls++;

        return $this->authorizationResult;
    }

    private function key(AccountsQuery $query): string
    {
        return $query->identityId.'|'.$query->product;
    }
}
