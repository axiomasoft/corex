<?php

declare(strict_types=1);

namespace CoreX\Time\Laravel;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
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
