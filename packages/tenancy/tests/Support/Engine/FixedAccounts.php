<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support\Engine;

use CoreX\Context\Data\AccountRef;
use CoreX\Tenancy\Account\Contracts\AccountRead;
use Override;

/** AccountRead over a fixed id => status map; owners are never needed by the engine. */
final class FixedAccounts implements AccountRead
{
    /** @param array<string, string> $statuses */
    public function __construct(public array $statuses) {}

    #[Override]
    public function ownerIdentityId(string $accountId, ?string $connectionName = null): ?string
    {
        return null;
    }

    #[Override]
    public function find(string $accountId, ?string $connectionName = null): ?AccountRef
    {
        return isset($this->statuses[$accountId])
            ? new AccountRef(id: $accountId, slug: 'acc-'.$accountId, status: $this->statuses[$accountId], features: [], limits: [])
            : null;
    }
}
