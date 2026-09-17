<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Data;

use CoreX\Recurrence\Data\ExpansionCursor;
use CoreX\Tasks\Data\TaskReference;

final readonly class MaterializationReceipt
{
    /** @param list<TaskReference> $taskRefs */
    public function __construct(
        public string $version,
        public array $taskRefs,
        public string $pageDigest,
        public ?string $committedPrefix,
        public ?ExpansionCursor $cursor,
        public int $created,
        public int $noop,
        public int $conflict,
        public bool $retired,
        public bool $unknown,
    ) {}
}
