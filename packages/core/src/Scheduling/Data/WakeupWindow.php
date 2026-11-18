<?php

declare(strict_types=1);

namespace CoreX\Scheduling\Data;

use DateTimeImmutable;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class WakeupWindow
{
    public function __construct(public DateTimeImmutable $start, public DateTimeImmutable $end, public string $period)
    {
        if (! in_array($period, ['hourly', 'daily', 'custom'], true) || $start >= $end || $start->getOffset() !== 0 || $end->getOffset() !== 0) {
            throw new InvalidArgumentException('Invalid wakeup window.');
        }
    }
}
