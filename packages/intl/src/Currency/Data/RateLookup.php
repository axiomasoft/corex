<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Data;

final readonly class RateLookup
{
    public function __construct(
        public string $status,
        public ?RateSnapshot $snapshot,
    ) {}
}
