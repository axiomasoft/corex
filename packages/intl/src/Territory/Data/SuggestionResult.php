<?php

declare(strict_types=1);

namespace CoreX\Intl\Data;

final readonly class SuggestionResult
{
    /** @param list<json-map> $suggestions | @param array<string, null|bool|int|string|array> $provenance */
    public function __construct(
        public string $status,
        public array $suggestions,
        public ?string $source,
        public array $provenance,
    ) {}
}
