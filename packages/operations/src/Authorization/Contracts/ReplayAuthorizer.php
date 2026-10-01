<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Idempotency\Data\StoredOperationOutcome;
use CoreX\Operations\Invocation\Data\OperationInvocation;

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
