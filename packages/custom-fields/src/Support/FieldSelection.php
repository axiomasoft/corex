<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Support;

final readonly class FieldSelection
{
    /** @param list<string> $fields */
    public function __construct(public array $fields)
    {
        Validation::fieldList(values: $fields);
    }
}
