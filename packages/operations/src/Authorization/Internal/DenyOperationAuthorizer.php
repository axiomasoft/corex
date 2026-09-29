<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Internal;

use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Definition\Operation;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use Override;

/** @internal */
final class DenyOperationAuthorizer implements OperationAuthorizer
{
    #[Override]
    public function allows(Operation $operation, RequestContext $context): bool
    {
        return false;
    }
}
