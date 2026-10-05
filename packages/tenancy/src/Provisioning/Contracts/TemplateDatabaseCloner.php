<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Provisioning\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\AccountRef;

/** Account provisioning reaches the clone driver only through this port. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
interface TemplateDatabaseCloner
{
    public function databaseExists(string $name): bool;

    public function createForAccount(AccountRef $account): bool;
}
