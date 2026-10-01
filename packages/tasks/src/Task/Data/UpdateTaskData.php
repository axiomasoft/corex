<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Data;

use CoreX\Attributes\Api;
use CoreX\Tasks\Task\Enums\TaskPriority;
use DateTimeImmutable;

#[Api(since: '1.0')]
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
