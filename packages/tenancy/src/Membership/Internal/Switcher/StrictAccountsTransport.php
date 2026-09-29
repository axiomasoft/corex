<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Internal\Switcher;

use CoreX\Tenancy\Membership\Contracts\AccountsReadTransport;
use CoreX\Tenancy\Membership\Data\AccountsQuery;
use CoreX\Tenancy\Membership\Data\AvailableAccounts;
use CoreX\Tenancy\Membership\Internal\Transport\StrictTransport;

/** @internal Explicitly composed accounts profile; never provider-registered. */
final readonly class StrictAccountsTransport implements AccountsReadTransport
{
    public function __construct(private StrictTransport $transport, private string $accountsUri) {}

    public function accountsFor(AccountsQuery $query): AvailableAccounts
    {
        return $this->transport->accountsFor(query: $query, accountsUri: $this->accountsUri);
    }
}
