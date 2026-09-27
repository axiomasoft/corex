<?php

declare(strict_types=1);

namespace CoreX\Tasks\Data;

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
