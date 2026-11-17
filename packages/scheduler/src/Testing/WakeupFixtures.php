<?php

declare(strict_types=1);

namespace CoreX\Scheduler\Testing;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Scheduler\Internal\WakeupHandlerRegistry;

/** Identifies existing bindings without exposing internal names to consumer tests. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WakeupFixtures
{
    public const string SCHEMA = WakeupHandlerRegistry::Schema;

    public const string POINT = WakeupHandlerRegistry::Point;

    public static function registryClass(): string
    {
        return WakeupHandlerRegistry::class;
    }
}
