<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\DatabaseConnectionConfig;

/**
 * Full Laravel connection config for an account's tenant database (host/port
 * of the cell's pooler, database, username). Boxed installs never bind this
 * — only cloud (axiomasoft/corex-tenancy) resolves real accounts.
 *
 * @internal spec: B-11 §3.1, D13
 */
interface AccountConnectionResolver
{
    public function resolve(AccountRef $account): DatabaseConnectionConfig;
}
