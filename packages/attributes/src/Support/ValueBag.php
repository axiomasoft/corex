<?php

declare(strict_types=1);

namespace CoreX\Attributes\Support;

final readonly class ValueBag
{
    /** @param array<array-key, mixed> $values */
    public function __construct(public array $values)
    {
        Validation::jsonMap(value: $values, name: 'attribute values');
    }
}
