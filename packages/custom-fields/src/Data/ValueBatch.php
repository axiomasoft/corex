<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

/** @phpstan-import-type JsonMap from ValueBag */
#[Api(since: '1.0')]
final readonly class ValueBatch
{
    /** @param list<ValueBag> $records */
    public function __construct(public array $records)
    {
        self::validateInput($records);
    }

    /** Runtime validation also rejects values from untyped integration callers.
     * @param  array<array-key, mixed>  $records
     */
    private static function validateInput(/** Runtime input. */
        array $records): void
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
