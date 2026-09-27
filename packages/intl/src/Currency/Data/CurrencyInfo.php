<?php

declare(strict_types=1);

namespace CoreX\Intl\Data;

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
