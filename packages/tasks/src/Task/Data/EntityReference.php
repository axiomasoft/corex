<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Data;

final readonly class EntityReference
{
    public function __construct(
        public string $type,
        public string $id,
    ) {}
}
