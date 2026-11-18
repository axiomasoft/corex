<?php

declare(strict_types=1);

namespace CoreX\Intl\Translation\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class TranslationResult
{
    public function __construct(
        public string $status,
        public ?string $value,
        public ?string $usedLocale,
        public bool $wasFallback,
    ) {}
}
