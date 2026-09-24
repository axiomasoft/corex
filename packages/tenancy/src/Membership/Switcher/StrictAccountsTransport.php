<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Switcher;

use CoreX\Tenancy\Membership\Transport\StrictTransport;

/** @internal Explicitly composed accounts profile; never provider-registered. */
final readonly class StrictAccountsTransport implements AccountsReadTransport
{
    public function __construct(private StrictTransport $transport, private string $accountsUri) {}

    public function accountsFor(AccountsQuery $query): AccountsResult
    {
        return $this->transport->accountsFor(query: $query, accountsUri: $this->accountsUri);
    }
}
