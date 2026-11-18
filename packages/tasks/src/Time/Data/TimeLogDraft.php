<?php

declare(strict_types=1);

namespace CoreX\Tasks\Time\Data;

use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Time\Enums\TimeLogSource;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
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
