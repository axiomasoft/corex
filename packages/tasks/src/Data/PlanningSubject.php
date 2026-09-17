<?php

declare(strict_types=1);

namespace CoreX\Tasks\Data;

final readonly class PlanningSubject
{
    public function __construct(
        public string $workspaceId,
        public ?string $projectId,
        public ?string $taskId,
        public ?string $userId,
    ) {}
}
