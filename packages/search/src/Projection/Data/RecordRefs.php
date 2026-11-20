<?php

declare(strict_types=1);

namespace CoreX\Search\Projection\Data;

use CoreX\Data\Data\RecordRef;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class RecordRefs
{
    /** @param list<RecordRef> $records */
    public function __construct(public array $records)
    {
        ProjectionInput::objects($records, RecordRef::class);
    }
}
