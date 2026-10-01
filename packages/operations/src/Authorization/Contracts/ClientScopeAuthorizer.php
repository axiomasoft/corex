<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Invocation\Contracts\RequestContext;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ClientScopeAuthorizer
{
    /** Return true only when the current client owns every scope declared by the operation. */
    public function allows(Operation $operation, RequestContext $context): bool;
}
