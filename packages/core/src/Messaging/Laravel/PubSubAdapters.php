<?php

declare(strict_types=1);

namespace CoreX\Messaging\Laravel;

use CoreX\Messaging\Internal\RedisPubSub;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Adapter availability for configuration diagnostics, without constructing a driver. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class PubSubAdapters
{
    public static function redisAvailable(): bool
    {
        return class_exists(RedisPubSub::class);
    }
}
