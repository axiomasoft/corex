<?php

declare(strict_types=1);

namespace CoreX\Api\Internal;

use CoreX\Api\Contracts\ClientScopeAuthorizer;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Operation;
use Override;

/** @internal Allows legacy unscoped operations and fails closed once scopes are declared. */
final class DenyClientScopeAuthorizer implements ClientScopeAuthorizer
{
    #[Override]
    public function allows(Operation $operation, RequestContext $context): bool
    {
        return $operation->requiredScopes === [];
    }
}
