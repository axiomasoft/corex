<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Data;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ThrottleStatus
{
    public function __construct(
        public int $capacity,
        public int $available,
        public int $restoreRate,
        public DateTimeImmutable $observedAt,
    ) {
        if ($capacity < 1 || $available < 0 || $available > $capacity || $restoreRate < 1) {
            throw new InvalidArgumentException('Throttle status values are outside their safe bounds.');
        }
    }
}
