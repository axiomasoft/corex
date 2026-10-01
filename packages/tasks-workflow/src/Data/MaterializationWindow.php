<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class MaterializationWindow
{
    public function __construct(
        public string $start,
        public string $end,
        public int $aheadDays = 30,
        public int $pageLimit = 100,
        public int $maxSeconds = 5,
    ) {}
}
