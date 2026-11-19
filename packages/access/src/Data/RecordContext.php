<?php

declare(strict_types=1);

namespace CoreX\Access\Data;

use Packman\Attributes\Api;

/** Contextual role-matrix cell, such as a pipeline stage. */
#[Api(since: '1.0')]
final readonly class RecordContext
{
    public function __construct(
        public string $type,
        public string $id,
    ) {}
}
