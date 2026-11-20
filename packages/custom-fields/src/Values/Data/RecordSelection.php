<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Values\Data;

use CoreX\CustomFields\Types\Data\FieldList;
use CoreX\Data\Data\RecordRef;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class RecordSelection
{
    /** @param list<RecordRef> $records */
    public function __construct(public array $records)
    {
        FieldList::check($records, [RecordRef::class], 1, 50);
        $seen = [];
        foreach ($records as $record) {
            if (isset($seen[$record->entity][$record->id])) {
                throw new InvalidArgumentException('Expected unique record references.');
            }
            $seen[$record->entity][$record->id] = true;
        }
    }
}
