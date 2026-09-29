<?php

declare(strict_types=1);

namespace CoreX\Tasks\Sprint\Data;

use CoreX\Tasks\Sprint\Models\Sprint;

final readonly class SprintReference
{
    public const string TYPE = 'tsk.sprint';

    public function __construct(
        public string $id,
        public string $type,
        public string $display,
    ) {}

    public static function fromSprint(Sprint $sprint): self
    {
        return new self(
            id: (string) $sprint->getKey(),
            type: self::TYPE,
            display: $sprint->name,
        );
    }

    /** @return array{id: string, type: string, display: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'display' => $this->display,
        ];
    }

    /** @param array{id: string, type: string, display: string} $stored */
    public static function fromStored(array $stored): self
    {
        return new self(
            id: $stored['id'],
            type: $stored['type'],
            display: $stored['display'],
        );
    }
}
