<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Support;

use InvalidArgumentException;

final readonly class ValuePatch
{
    /** @param array<array-key, mixed> $values */
    public function __construct(
        public array $values,
        public ?string $expectedSchemaHash = null,
    ) {
        if ($values === []) {
            throw new InvalidArgumentException('An attribute write patch must not be empty.');
        }

        Validation::jsonMap(value: $values, name: 'attribute patch');

        if ($expectedSchemaHash !== null) {
            Validation::sha256(value: $expectedSchemaHash, name: 'expected schema hash');
        }
    }
}
