<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Data;

use CoreX\Attributes\Api;
use CoreX\Tenancy\Membership\Enums\ActorType;
use CoreX\Tenancy\Membership\Internal\Transport\CentralValue;

#[Api(since: '1.0')]
final readonly class ActorRef
{
    public function __construct(
        public ActorType $type,
        string $id,
    ) {
        $this->id = CentralValue::uuid(value: $id, field: 'actor.id');
    }

    public string $id;
}
