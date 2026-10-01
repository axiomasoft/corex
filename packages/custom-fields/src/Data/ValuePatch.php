<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\Attributes\Api;
use CoreX\CustomFields\Internal\Support\Validation;
use InvalidArgumentException;

/** @phpstan-import-type JsonMap from \CoreX\CustomFields\Data\ValueBag */
#[Api(since: '1.0')]
final readonly class ValuePatch
{
    /** @param JsonMap $values */
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
