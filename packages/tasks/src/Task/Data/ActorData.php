<?php

declare(strict_types=1);

namespace CoreX\Tasks\Data;

use CoreX\Enums\ActorType;

final readonly class ActorData
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public ActorType $type,
        public ?string $id,
        public array $context = [],
    ) {}

    /** @return array{type: string, id: ?string, context: array<string, mixed>} */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'id' => $this->id,
            'context' => $this->context,
        ];
    }
}
