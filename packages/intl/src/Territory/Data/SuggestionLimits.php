<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Data;

final readonly class SuggestionLimits
{
    public function __construct(
        public int $timeoutSeconds = 2,
        public int $results = 20,
        public int $maxResponseBytes = 65536,
    ) {}
}
