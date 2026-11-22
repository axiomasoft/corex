<?php

declare(strict_types=1);

namespace CoreX\Search\Projection\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class SourceRecords
{
    /** @param list<SourceRecord> $records */
    public function __construct(public array $records)
    {
        ProjectionInput::objects(items: $records, class: SourceRecord::class);
    }
}
