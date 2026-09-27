<?php

declare(strict_types=1);

namespace CoreX\Api\Data;

use InvalidArgumentException;

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
