<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Testing;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Wakeup\Internal\SystemClock;
use CoreX\Wakeup\Internal\WakeupHandlerRegistry;

/** Identifies existing bindings without exposing internal names to consumer tests. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WakeupFixtures
{
    public const SCHEMA = WakeupHandlerRegistry::Schema;

    public const POINT = WakeupHandlerRegistry::Point;

    public static function registryClass(): string
    {
        return WakeupHandlerRegistry::class;
    }

    public static function systemClockClass(): string
    {
        return SystemClock::class;
    }
}
