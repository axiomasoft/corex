<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\Membership\Data\AccountsQuery;
use CoreX\Tenancy\Membership\Data\AvailableAccounts;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface AccountsReadTransport
{
    public function accountsFor(AccountsQuery $query): AvailableAccounts;
}
