<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Internal;

use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Definition\Data\Operation;
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
