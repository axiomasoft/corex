<?php

declare(strict_types=1);

namespace Flex\Crm\Contracts;

use Flex\Crm\Data\ExecutionContext;

/**
 * Application-owned authentication boundary, resolved afresh for every operation.
 * Authenticate actor provenance and current account admission before returning.
 * Non-user actors require an admitted run-as user; account-level access is an
 * explicit capability, never inferred from a role name or missing workspace.
 * Throw on missing/revoked authentication. Never deserialize request actor here.
 */
interface CrmExecutionContext
{
    public function current(): ExecutionContext;
}
