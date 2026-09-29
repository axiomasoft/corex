<?php

declare(strict_types=1);

namespace CoreX\Observability\Data;

use CoreX\Observability\Enums\OperationOutcome;
use CoreX\Observability\Internal\OperationContext;
use InvalidArgumentException;

final readonly class OperationCompleted
{
    public function __construct(
        public OperationContext $context,
        public int $durationMilliseconds,
        public OperationOutcome $outcome,
    ) {
        if ($durationMilliseconds < 0) {
            throw new InvalidArgumentException('Duration cannot be negative.');
        }
    }
}
