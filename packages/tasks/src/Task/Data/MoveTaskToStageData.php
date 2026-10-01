<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class MoveTaskToStageData
{
    public function __construct(
        public string $taskId,
        public string $stageId,
        public ActorData $actor,
    ) {}
}
