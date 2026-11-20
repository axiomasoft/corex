<?php

declare(strict_types=1);

namespace CoreX\Search\Projection\Data;

use CoreX\Data\Data\RecordRef;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class SourceRecord
{
    public function __construct(public RecordRef $record, public string $revision, public ProjectedValues $values)
    {
        ProjectionInput::digest($revision);
    }
}
