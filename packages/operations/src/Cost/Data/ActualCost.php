<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class ActualCost
{
    public function __construct(
        public int $units,
    ) {
        if ($units < 0) {
            throw new InvalidArgumentException('Actual cost units must be nonnegative.');
        }
    }
}
