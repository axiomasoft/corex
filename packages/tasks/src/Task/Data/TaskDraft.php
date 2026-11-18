<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Data;

use CoreX\Tasks\Task\Enums\TaskPriority;
use DateTimeImmutable;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class TaskDraft
{
    /** @param list<EntityReference> $targets */
    public function __construct(
        public string $workspaceId,
        public string $title,
        public ActorData $actor,
        public ?string $description = null,
        public TaskPriority $priority = TaskPriority::Normal,
        public ?string $projectId = null,
        public ?string $sprintId = null,
        public ?string $stageId = null,
        public ?string $parentId = null,
        public ?string $authorId = null,
        public ?string $assigneeId = null,
        public ?DateTimeImmutable $startsAt = null,
        public ?DateTimeImmutable $dueAt = null,
        public ?int $estimateMinutes = null,
        public array $targets = [],
    ) {}
}
