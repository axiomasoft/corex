<?php

declare(strict_types=1);

namespace CoreX\Attributes\Support;

use CoreX\Support\Data\RecordRef;
use InvalidArgumentException;

final readonly class RecordSelection
{
    /** @param array<array-key, mixed> $records */
    public function __construct(public array $records)
    {
        if (! array_is_list($records) || $records === [] || count($records) > 50) {
            throw new InvalidArgumentException('Records must be a non-empty list of at most 50 records.');
        }

        $seen = [];
        foreach ($records as $record) {
            if (! $record instanceof RecordRef) {
                throw new InvalidArgumentException('Records must contain record references.');
            }

            $key = $record->entity."\0".$record->id;

            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Records must be unique.');
            }

            $seen[$key] = true;
        }
    }
}
