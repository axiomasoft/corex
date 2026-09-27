<?php

declare(strict_types=1);

namespace CoreX\Intl\Data;

final readonly class FormatPolicy
{
    public function __construct(
        public string $mode,
        public string $fallback,
    ) {}
}
