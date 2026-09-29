<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Data;

final readonly class MoveTaskToStageData
{
    public function __construct(
        public string $taskId,
        public string $stageId,
        public ActorData $actor,
    ) {}
}
