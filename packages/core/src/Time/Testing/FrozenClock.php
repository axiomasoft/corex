<?php

declare(strict_types=1);

namespace CoreX\Time\Testing;

use DateInterval;
use DateTimeImmutable;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
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
