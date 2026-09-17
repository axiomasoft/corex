<?php

declare(strict_types=1);

namespace CoreX\Intl\Data;

final readonly class TranslationResult
{
    public function __construct(
        public string $status,
        public ?string $value,
        public ?string $usedLocale,
        public bool $wasFallback,
    ) {}
}
