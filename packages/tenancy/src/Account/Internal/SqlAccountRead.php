<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Internal;

use CoreX\Context\Data\AccountRef;
use CoreX\Tenancy\Account\Contracts\AccountRead;
use CoreX\Tenancy\Account\Internal\Models\Account;
use Packman\Attributes\Internal;

#[Internal]
final class SqlAccountRead implements AccountRead
{
    public function ownerIdentityId(string $accountId, ?string $connectionName = null): ?string
    {
        $account = Account::on($connectionName)->find($accountId);

        return $account?->owner_identity_id;
    }

    public function find(string $accountId, ?string $connectionName = null): ?AccountRef
    {
        $account = Account::on($connectionName)->find($accountId);

        return $account === null ? null : new AccountRef(
            id: $account->id, slug: ($account->slug ?? ''),
            status: $account->status, features: [], limits: [],
        );
    }
}
