<?php

declare(strict_types=1);

namespace CoreX\Foundation\Internal;

use CoreX\Audit\Contracts\CurrentActor;
use Packman\Context\Contracts\ActorResolverInterface;
use Packman\Context\Data\ActorRef;

final readonly class PackmanActorResolver implements ActorResolverInterface
{
    public function current(): ActorRef
    {
        return new ActorRef(type: CurrentActor::type()->value, id: CurrentActor::id());
    }
}
