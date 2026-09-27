<?php

declare(strict_types=1);

namespace CoreX\Tasks\Data;

use CoreX\Tasks\Enums\TaskPriority;
use DateTimeImmutable;

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
