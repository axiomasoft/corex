<?php

declare(strict_types=1);

namespace CoreX\Intl\Data;

final readonly class LocalizedText
{
    /** @param array<string,string|null> $map */
    public function __construct(
        public ?string $declaredField,
        public ?string $schemaHash,
        public array $map,
    ) {}
}
