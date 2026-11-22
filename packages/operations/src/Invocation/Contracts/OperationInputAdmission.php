<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Contracts;

use CoreX\Operations\Invocation\Data\OperationInvocation;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface OperationInputAdmission
{
    /** Check the invocation without opening a receipt, reserving budget or calling its handler. */
    public function assertInput(OperationInvocation $invocation): void;
}
