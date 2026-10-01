<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Data;

use CoreX\Attributes\Api;
use CoreX\Audit\Enums\ActorType;
use CoreX\Data\Data\JsonDocument;

#[Api(since: '1.0')]
final readonly class ActorData
{
    /** Immutable JSON value. */
    public function __construct(public ActorType $type, public ?string $id, public JsonDocument $context = new JsonDocument('[]')) {}

    /** @return array{type: string, id: ?string, context: JsonDocument} */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'id' => $this->id,
            'context' => $this->context,
        ];
    }
}
