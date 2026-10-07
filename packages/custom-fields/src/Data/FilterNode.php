<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\Attributes\Api;
use CoreX\CustomFields\Internal\Support\Validation;
use InvalidArgumentException;

/** @phpstan-import-type JsonMap from ValueBag */
#[Api(since: '1.0')]
final readonly class FilterNode
{
    /** @param JsonMap $tree */
    public function __construct(public array $tree)
    {
        if ($tree === []) {
            throw new InvalidArgumentException('Filter tree must not be empty.');
        }

        Validation::jsonMap(value: $tree, name: 'filter tree');
    }
}
