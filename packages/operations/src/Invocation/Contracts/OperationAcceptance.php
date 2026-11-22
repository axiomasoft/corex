<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Contracts;

use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\Data\AcceptedOperation;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface OperationAcceptance
{
    /** @param array<array-key, mixed> $result */
    public function acceptance(RequestContext $context, array $result): ?AcceptedOperation;
}
