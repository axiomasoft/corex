<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Contracts;

use CoreX\Operations\Idempotency\Data\StoredOperationOutcome;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Side-effect-free current-authority projection applied to fresh and replayed outcomes. */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ReplayAuthorizer
{
    public function authorize(
        OperationInvocation $invocation,
        StoredOperationOutcome $outcome,
        bool $replayed,
    ): StoredOperationOutcome;
}
