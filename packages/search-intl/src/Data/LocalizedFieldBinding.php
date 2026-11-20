<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Data;

use CoreX\Data\Enums\FieldUse;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class LocalizedFieldBinding
{
    /** @param list<FieldUse> $uses */
    public function __construct(
        public string $column,
        public string $declaredField,
        public string $schemaHash,
        public array $uses = [FieldUse::Read, FieldUse::Search],
    ) {}
}
