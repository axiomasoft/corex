<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class PlanningSubject
{
    public function __construct(
        public string $workspaceId,
        public ?string $projectId,
        public ?string $taskId,
        public ?string $userId,
    ) {}
}
