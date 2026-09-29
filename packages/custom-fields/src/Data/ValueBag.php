<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\CustomFields\Internal\Support\Validation;

final readonly class ValueBag
{
    /** @param array<array-key, mixed> $values */
    public function __construct(public array $values)
    {
        Validation::jsonMap(value: $values, name: 'attribute values');
    }
}
