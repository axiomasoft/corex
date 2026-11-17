<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Data;

use CoreX\Attributes\Api;
use CoreX\Recurrence\Data\ExpansionCursor;
use CoreX\Tasks\Task\Data\TaskReference;

#[Api(since: '1.0')]
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
