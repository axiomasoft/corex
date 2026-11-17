<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\DatabaseConnectionConfig;

/**
 * Host-owned, verified database identity used to admit account-scoped work.
 * A Tenant host derives this projection from its trusted resource binding;
 * resolving an identity never switches, provisions or opens a connection.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface AccountConnectionResolver
{
    public function resolve(AccountRef $account): DatabaseConnectionConfig;
}
