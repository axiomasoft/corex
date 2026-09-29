<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Data;

use CoreX\Tasks\Task\Models\Task;

final readonly class TaskReference
{
    public const string TYPE = 'tsk.task';

    public function __construct(
        public string $id,
        public string $type,
        public string $display,
    ) {}

    public static function fromTask(Task $task): self
    {
        return new self(
            id: (string) $task->getKey(),
            type: self::TYPE,
            display: $task->title,
        );
    }
}
