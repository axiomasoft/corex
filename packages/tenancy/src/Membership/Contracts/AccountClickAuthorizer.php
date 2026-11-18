<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Contracts;

use CoreX\Tenancy\Membership\Data\AccountsQuery;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface AccountClickAuthorizer
{
    public function authorize(AccountsQuery $query, string $accountId): bool;
}
