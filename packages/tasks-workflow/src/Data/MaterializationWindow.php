<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Data;

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
