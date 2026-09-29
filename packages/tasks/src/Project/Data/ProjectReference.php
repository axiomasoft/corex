<?php

declare(strict_types=1);

namespace CoreX\Tasks\Project\Data;

use CoreX\Tasks\Project\Models\Project;

final readonly class ProjectReference
{
    public const string TYPE = 'tsk.project';

    public function __construct(
        public string $id,
        public string $type,
        public string $display,
    ) {}

    public static function fromProject(Project $project): self
    {
        return new self(
            id: (string) $project->getKey(),
            type: self::TYPE,
            display: $project->name,
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
