<?php

declare(strict_types=1);

namespace CoreX\Foundation\Internal;

use Closure;
use CoreX\Audit\Internal\CurrentActor;
use Packman\Attributes\Internal;
use Packman\Context\Contracts\ActorResolverInterface;
use Packman\Context\Data\ActorRef;

#[Internal]
final readonly class PackmanActorResolver implements ActorResolverInterface
{
    /** @param Closure(): CurrentActor $actor */
    public function __construct(private Closure $actor) {}

    public function current(): ActorRef
    {
        $actor = ($this->actor)();

        return new ActorRef(type: $actor->type()->value, id: $actor->id());
    }
}
