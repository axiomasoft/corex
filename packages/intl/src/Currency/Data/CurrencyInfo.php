<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class CurrencyInfo
{
    public function __construct(
        public string $isoCode,
        public int $exponent,
        public ?string $name,
        public ?string $symbol,
        public string $metadataVersion,
        public string $metadataHash,
    ) {}
}
