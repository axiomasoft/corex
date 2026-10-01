<?php

declare(strict_types=1);

namespace CoreX\Time\Testing;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use DateInterval;
use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
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
