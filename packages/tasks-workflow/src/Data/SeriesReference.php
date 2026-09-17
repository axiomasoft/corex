<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Data;

final readonly class SeriesReference
{
    public function __construct(
        public string $id,
        public string $immutableRevision,
        public string $definitionHash,
    ) {}
}
