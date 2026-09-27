<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\Operation;

interface ClientScopeAuthorizer
{
    /** Return true only when the current client owns every scope declared by the operation. */
    public function allows(Operation $operation, RequestContext $context): bool;
}
