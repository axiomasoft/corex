<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Data;

use CoreX\Attributes\Api;
use CoreX\Tasks\Task\Enums\TaskStatus;

#[Api(since: '1.0')]
final readonly class TaskView
{
    public function __construct(
        public string $id,
        public ?string $workspaceId,
        public ?string $projectId,
        public ?string $stageId,
        public string $title,
        public TaskStatus $status,
        public ?string $authorId,
        public ?string $assigneeId,
        public bool $requiresAcceptance,
    ) {}
}
