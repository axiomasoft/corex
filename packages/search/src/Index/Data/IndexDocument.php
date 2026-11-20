<?php

declare(strict_types=1);

namespace CoreX\Search\Index\Data;

use CoreX\Data\Data\RecordRef;
use CoreX\Search\Projection\Data\ProjectedValues;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class IndexDocument
{
    public function __construct(public RecordRef $record, public ProjectedValues $values) {}
}
