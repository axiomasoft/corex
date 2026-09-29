<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Data;

final readonly class CompleteTaskData
{
    public function __construct(
        public string $taskId,
        public ActorData $actor,
    ) {}
}
