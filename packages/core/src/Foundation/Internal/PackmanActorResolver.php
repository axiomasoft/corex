<?php

declare(strict_types=1);

namespace CoreX\Foundation\Internal;

use Closure;
use Packman\Attributes\Internal;
use Packman\Context\Contracts\ActorResolverInterface;
use Packman\Context\Data\ActorRef;

#[Internal]
final readonly class PackmanActorResolver implements ActorResolverInterface
{
    /** @param  Closure(): ActorRef  $actor */
    public function __construct(private Closure $actor) {}

    public function current(): ActorRef
    {
        return ($this->actor)();
    }
}
