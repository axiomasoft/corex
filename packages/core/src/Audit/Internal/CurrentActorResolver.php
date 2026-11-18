<?php

declare(strict_types=1);

namespace CoreX\Audit\Internal;

use Illuminate\Container\Container;
use Packman\Attributes\Internal;

/**
 * Bridge of the static `Audit\Contracts\CurrentActor` entry point: the static call has no
 * constructor to carry dependencies, so the scoped actor of the running cycle is resolved here.
 *
 * @internal Composition helper of the audit module.
 */
#[Internal]
final class CurrentActorResolver
{
    public static function current(): CurrentActor
    {
        return Container::getInstance()->make(CurrentActor::class);
    }
}
