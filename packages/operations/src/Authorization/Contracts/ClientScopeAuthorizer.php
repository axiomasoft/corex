<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Contracts;

use CoreX\Operations\Definition\Operation;
use CoreX\Operations\Invocation\Contracts\RequestContext;

interface ClientScopeAuthorizer
{
    /** Return true only when the current client owns every scope declared by the operation. */
    public function allows(Operation $operation, RequestContext $context): bool;
}
