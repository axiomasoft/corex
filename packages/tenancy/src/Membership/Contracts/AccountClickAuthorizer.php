<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Contracts;

use CoreX\Tenancy\Membership\Data\AccountsQuery;

/** @internal The caller must bind this to central authorization at future adoption. */
interface AccountClickAuthorizer
{
    public function authorize(AccountsQuery $query, string $accountId): bool;
}
