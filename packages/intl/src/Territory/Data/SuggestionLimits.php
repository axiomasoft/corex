<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class SuggestionLimits
{
    public function __construct(
        public int $timeoutSeconds = 2,
        public int $results = 20,
        public int $maxResponseBytes = 65536,
    ) {}
}
