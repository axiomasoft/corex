<?php

declare(strict_types=1);

namespace CoreX\Observability\Data;

use CoreX\Attributes\Api;
use CoreX\Observability\Enums\OperationOutcome;
use InvalidArgumentException;

#[Api(since: '1.0')]
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
