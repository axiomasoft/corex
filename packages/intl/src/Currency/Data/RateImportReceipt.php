<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class RateImportReceipt
{
    public function __construct(
        public string $status,
        public int $generation,
        public int $imported,
        public int $noop,
        public int $conflicts,
    ) {}
}
