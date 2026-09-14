<?php

declare(strict_types=1);

namespace CoreX\Api\Internal;

use CoreX\Api\Contracts\OperationAuthorizer;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Operation;
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
