<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\Operation;

interface OperationAuthorizer
{
    /** Capability check; handlers must additionally scope individual resource lookups. */
    public function allows(Operation $operation, RequestContext $context): bool;
}
