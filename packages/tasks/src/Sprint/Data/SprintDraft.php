<?php

declare(strict_types=1);

namespace CoreX\Tasks\Sprint\Data;

use CoreX\Attributes\Api;
use CoreX\Tasks\Task\Data\ActorData;

#[Api(since: '1.0')]
final readonly class SprintDraft
{
    public function __construct(
        public string $projectId,
        public string $name,
        public ?string $startDate,
        public ?string $endDate,
        public ActorData $actor,
    ) {}
}
