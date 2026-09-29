<?php

declare(strict_types=1);

namespace CoreX\Observability\Data;

use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Observability\Internal\OperationContext;
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
