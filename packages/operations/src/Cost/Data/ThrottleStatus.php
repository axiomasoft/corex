<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Data;

use CoreX\Attributes\Api;
use DateTimeImmutable;
use InvalidArgumentException;

#[Api(since: '1.0')]
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
