<?php

declare(strict_types=1);

namespace CoreX\Tasks\Time\Data;

use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Time\Enums\TimeLogSource;

final readonly class TimeLogDraft
{
    public function __construct(
        public string $taskId,
        public string $userId,
        public int $minutes,
        public string $spentOn,
        public ?string $comment,
        public TimeLogSource $source,
        public ActorData $actor,
    ) {}
}
