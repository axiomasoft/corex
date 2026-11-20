<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\WorkspaceRef;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface WorkScopeDirectory
{
    public function account(string $accountId): AccountRef;

    /** Resolve on the already verified account database. */
    public function workspace(string $workspaceId): WorkspaceRef;
}
