<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class SeriesReference
{
    public function __construct(
        public string $id,
        public string $immutableRevision,
        public string $definitionHash,
    ) {}
}
