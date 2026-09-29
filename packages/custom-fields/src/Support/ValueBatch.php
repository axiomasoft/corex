<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Support;

use InvalidArgumentException;

final readonly class ValueBatch
{
    /** @param array<array-key, mixed> $records */
    public function __construct(public array $records)
    {
        if (! array_is_list($records)) {
            throw new InvalidArgumentException('Value batch records must be a list.');
        }

        foreach ($records as $record) {
            if (! $record instanceof ValueBag) {
                throw new InvalidArgumentException('Value batch records must contain value bags.');
            }
        }
    }
}
