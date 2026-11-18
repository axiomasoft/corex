<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class RateLookup
{
    public function __construct(
        public string $status,
        public ?RateSnapshot $snapshot,
    ) {}
}
