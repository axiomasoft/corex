<?php

declare(strict_types=1);

namespace CoreX\Attributes\Support;

use InvalidArgumentException;

final readonly class FilterNode
{
    /** @param array<array-key, mixed> $tree */
    public function __construct(public array $tree)
    {
        if ($tree === []) {
            throw new InvalidArgumentException('Filter tree must not be empty.');
        }

        Validation::jsonMap(value: $tree, name: 'filter tree');
    }
}
