<?php

declare(strict_types=1);

namespace CoreX\Tasks\Project\Data;

use CoreX\Tasks\Task\Data\ActorData;

final readonly class ProjectDraft
{
    public function __construct(
        public string $workspaceId,
        public string $name,
        public ActorData $actor,
    ) {}
}
