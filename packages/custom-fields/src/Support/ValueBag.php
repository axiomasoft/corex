<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Support;

final readonly class ValueBag
{
    /** @param array<array-key, mixed> $values */
    public function __construct(public array $values)
    {
        Validation::jsonMap(value: $values, name: 'attribute values');
    }
}
