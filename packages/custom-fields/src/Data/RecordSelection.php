<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\Attributes\Api;
use CoreX\Data\Data\RecordRef;
use InvalidArgumentException;

/** @phpstan-import-type JsonMap from \CoreX\CustomFields\Data\ValueBag */
#[Api(since: '1.0')]
final readonly class RecordSelection
{
    /** @param list<RecordRef> $records */
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
