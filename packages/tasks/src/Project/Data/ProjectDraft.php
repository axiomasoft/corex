<?php

declare(strict_types=1);

namespace CoreX\Tasks\Data;

final readonly class ProjectDraft
{
    public function __construct(
        public string $workspaceId,
        public string $name,
        public ActorData $actor,
    ) {}
}
