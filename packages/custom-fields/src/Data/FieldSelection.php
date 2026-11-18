<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\CustomFields\Internal\Support\Validation;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class FieldSelection
{
    /** @param list<string> $fields */
    public function __construct(public array $fields)
    {
        Validation::fieldList(values: $fields);
    }
}
