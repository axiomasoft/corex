<?php

declare(strict_types=1);

namespace CoreX\Audit\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Audit\Enums\ActorType;
use CoreX\Audit\Internal\CurrentActorResolver;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CurrentActor
{
    public static function type(): ActorType
    {
        return CurrentActorResolver::current()->type();
    }

    public static function id(): ?string
    {
        return CurrentActorResolver::current()->id();
    }

    /** @return array<string, mixed> */
    public static function context(): array
    {
        return CurrentActorResolver::current()->context();
    }
}
