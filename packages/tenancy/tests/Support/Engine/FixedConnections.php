<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support\Engine;

use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\DatabaseConnectionConfig;
use Override;
use RuntimeException;

final class FixedConnections implements AccountConnectionResolver
{
    /** @var array<string, RuntimeException> failure per account id */
    public array $failures = [];

    #[Override]
    public function resolve(AccountRef $account): DatabaseConnectionConfig
    {
        if (isset($this->failures[$account->id])) {
            throw $this->failures[$account->id];
        }

        return new DatabaseConnectionConfig('pgsql', 'db.test', 5432, 'db_'.$account->id, 'corex');
    }
}
