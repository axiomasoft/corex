<?php

declare(strict_types=1);

namespace CoreX\Intl\Formatting\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class FormatPolicy
{
    public function __construct(
        public string $mode,
        public string $fallback,
    ) {}
}
