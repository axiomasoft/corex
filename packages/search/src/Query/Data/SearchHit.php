<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Data;

use CoreX\Data\Data\RecordRef;
use CoreX\Search\Projection\Data\ProjectedValues;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class SearchHit
{
    public function __construct(public RecordRef $record, public ProjectedValues $projection) {}
}
