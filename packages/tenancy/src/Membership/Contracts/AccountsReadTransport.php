<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Contracts;

use CoreX\Tenancy\Membership\Data\AccountsQuery;
use CoreX\Tenancy\Membership\Data\AvailableAccounts;

/** @internal Composition seam for an eventual central accounts transport. */
interface AccountsReadTransport
{
    public function accountsFor(AccountsQuery $query): AvailableAccounts;
}
