<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Contracts;

use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Definition\Data\Operation;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface OperationFieldAuthorizer
{
    /** Paths are RFC 6901 pointers; host policy can narrow but never widen declarations. */
    public function allowsRead(Operation $operation, RequestContext $context, string $path): bool;

    /** Paths are RFC 6901 pointers; returning false rejects the complete operation input. */
    public function allowsWrite(Operation $operation, RequestContext $context, string $path): bool;
}
