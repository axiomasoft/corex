<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Data;

use DateTimeImmutable;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class InstantWindow
{
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {
        if ($end <= $start) {
            throw new InvalidArgumentException('An instant window must be half-open and non-empty.');
        }
    }
}
