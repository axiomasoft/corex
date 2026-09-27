<?php

declare(strict_types=1);

namespace CoreX\Notifications\Data;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DigestWindow
{
    public function __construct(public DateTimeImmutable $start, public DateTimeImmutable $end, public string $period)
    {
        if (! in_array($period, ['hourly', 'daily'], true) || $start >= $end) {
            throw new InvalidArgumentException('Invalid digest window.');
        }
    }
}
