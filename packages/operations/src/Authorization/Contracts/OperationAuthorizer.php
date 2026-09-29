<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Contracts;

use CoreX\Operations\Definition\Operation;
use CoreX\Operations\Invocation\Contracts\RequestContext;

interface OperationAuthorizer
{
    /** Capability check; handlers must additionally scope individual resource lookups. */
    public function allows(Operation $operation, RequestContext $context): bool;
}
