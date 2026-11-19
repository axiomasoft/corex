<?php

declare(strict_types=1);

namespace Flex\Crm\Context\Contracts;

use Flex\Crm\Context\Data\ExecutionContext;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Application-owned authentication boundary, resolved afresh for every operation.
 * Authenticate actor provenance and current account admission before returning.
 * Non-user actors require an admitted run-as user; account-level access is an
 * explicit capability, never inferred from a role name or missing workspace.
 * Throw on missing/revoked authentication. Never deserialize request actor here.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface CrmExecutionContext
{
    public function current(): ExecutionContext;
}
