<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Support;

final readonly class FilterPlan
{
    /** @param array<array-key, mixed> $tree */
    public function __construct(
        public string $schemaHash,
        public array $tree,
    ) {
        Validation::sha256(value: $schemaHash, name: 'schema hash');
        Validation::jsonMap(value: $tree, name: 'filter plan');
    }
}
