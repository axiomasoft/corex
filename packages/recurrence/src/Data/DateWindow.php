<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class DateWindow
{
    public function __construct(
        public string $start,
        public string $end,
    ) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) !== 1) {
            throw new InvalidArgumentException('A date window must use calendar dates.');
        }

        if ($end <= $start) {
            throw new InvalidArgumentException('A date window must be half-open and non-empty.');
        }
    }
}
