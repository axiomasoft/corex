<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Internal;

use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Definition\Data\Operation;
use Override;
use Packman\Attributes\Internal;

/** @internal */
#[Internal]
final class DenyOperationAuthorizer implements OperationAuthorizer
{
    #[Override]
    public function allows(Operation $operation, RequestContext $context): bool
    {
        return false;
    }
}
