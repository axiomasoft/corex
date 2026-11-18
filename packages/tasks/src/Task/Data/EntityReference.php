<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class EntityReference
{
    public function __construct(
        public string $type,
        public string $id,
    ) {}
}
