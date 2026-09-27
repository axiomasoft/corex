<?php

declare(strict_types=1);

namespace CoreX\Tasks\Data;

use CoreX\Tasks\Enums\TaskPriority;
use DateTimeImmutable;

final readonly class UpdateTaskData
{
    private const array FIELDS = [
        'title',
        'description',
        'priority',
        'assignee_id',
        'project_id',
        'starts_at',
        'due_at',
        'estimate_minutes',
    ];

    /**
     * @param  list<string>  $provided
     */
    public function __construct(
        public string $taskId,
        public ActorData $actor,
        public array $provided,
        public ?string $title = null,
        public ?string $description = null,
        public ?TaskPriority $priority = null,
        public ?string $assigneeId = null,
        public ?DateTimeImmutable $startsAt = null,
        public ?DateTimeImmutable $dueAt = null,
        public ?int $estimateMinutes = null,
        public ?string $projectId = null,
    ) {}

    public function has(string $field): bool
    {
        return in_array($field, $this->provided, true);
    }

    /** @return list<string> */
    public static function fields(): array
    {
        return self::FIELDS;
    }
}
