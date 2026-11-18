<?php

declare(strict_types=1);

namespace CoreX\Observability\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;

#[Api(since: '1.0')]
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
