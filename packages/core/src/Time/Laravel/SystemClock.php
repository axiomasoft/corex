<?php

declare(strict_types=1);

namespace CoreX\Time\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use DateTimeImmutable;
use DateTimeZone;
use Override;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SystemClock implements ClockInterface
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
