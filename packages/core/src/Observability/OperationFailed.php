<?php

declare(strict_types=1);

namespace CoreX\Observability;

use CoreX\Errors\Data\ErrorDescriptor;
use InvalidArgumentException;

final readonly class OperationFailed
{
    public function __construct(
        public OperationContext $context,
        public int $durationMilliseconds,
        public ErrorDescriptor $error,
    ) {
        if ($durationMilliseconds < 0) {
            throw new InvalidArgumentException('Duration cannot be negative.');
        }
    }
}
