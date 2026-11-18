<?php

declare(strict_types=1);

namespace CoreX\Intl\Translation\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class LocalizedText
{
    /** @param array<string,string|null> $map */
    public function __construct(
        public ?string $declaredField,
        public ?string $schemaHash,
        public array $map,
    ) {}
}
