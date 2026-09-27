<?php

declare(strict_types=1);

namespace CoreX\Support\Time;

use DateInterval;
use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

final class FrozenClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $at) {}

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->at;
    }

    public function advance(DateInterval $by): void
    {
        $this->at = $this->at->add($by);
    }
}
