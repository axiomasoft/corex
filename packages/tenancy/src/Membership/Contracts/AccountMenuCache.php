<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\Membership\Data\AccountsQuery;
use CoreX\Tenancy\Membership\Data\AvailableAccounts;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface AccountMenuCache
{
    public function versionFor(AccountsQuery $query): ?string;

    public function resultFor(AccountsQuery $query, string $membershipVersion): ?AvailableAccounts;

    public function put(AccountsQuery $query, AvailableAccounts $result, int $ttlSeconds): void;
}
