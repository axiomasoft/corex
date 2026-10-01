<?php

declare(strict_types=1);

namespace CoreX\Audit\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Audit\Enums\ActorType;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CurrentActor
{
    public static function type(): ActorType
    {
        return \CoreX\Audit\Internal\CurrentActor::type();
    }

    public static function id(): ?string
    {
        return \CoreX\Audit\Internal\CurrentActor::id();
    }

    /** @return array<string, mixed> */
    public static function context(): array
    {
        return \CoreX\Audit\Internal\CurrentActor::context();
    }
}
