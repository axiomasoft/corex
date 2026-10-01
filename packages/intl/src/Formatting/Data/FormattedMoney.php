<?php

declare(strict_types=1);

namespace CoreX\Intl\Formatting\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class FormattedMoney
{
    public function __construct(
        public string $text,
        public string $exactDecimal,
        public string $currency,
        public string $locale,
        public string $method,
        public bool $wasFallback,
        public ?string $icuVersion,
    ) {}
}
