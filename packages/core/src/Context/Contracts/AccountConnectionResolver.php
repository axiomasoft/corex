<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\DatabaseConnectionConfig;

/**
 * Full Laravel connection config for an account's tenant database (host/port
 * of the cell's pooler, database, username). Boxed installs never bind this
 * — only cloud (axiomasoft/corex-tenancy) resolves real accounts.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface AccountConnectionResolver
{
    public function resolve(AccountRef $account): DatabaseConnectionConfig;
}
