<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\Attributes\Api;
use CoreX\CustomFields\Internal\Support\Validation;

/** @phpstan-import-type JsonMap from \CoreX\CustomFields\Data\ValueBag */
#[Api(since: '1.0')]
final readonly class FilterPlan
{
    /** @param JsonMap $tree */
    public function __construct(
        public string $schemaHash,
        public array $tree,
    ) {
        Validation::sha256(value: $schemaHash, name: 'schema hash');
        Validation::jsonMap(value: $tree, name: 'filter plan');
    }
}
