<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Contracts;

use CoreX\Context\Data\AccountRef;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
interface AccountRead
{
    public function ownerIdentityId(string $accountId, ?string $connectionName = null): ?string;

    public function find(string $accountId, ?string $connectionName = null): ?AccountRef;
}
