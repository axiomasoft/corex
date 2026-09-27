<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\Data\OperationInvocation;
use CoreX\Api\Data\StoredOperationOutcome;

/** Side-effect-free current-authority projection applied to fresh and replayed outcomes. */
interface ReplayAuthorizer
{
    public function authorize(
        OperationInvocation $invocation,
        StoredOperationOutcome $outcome,
        bool $replayed,
    ): StoredOperationOutcome;
}
