<?php

declare(strict_types=1);

namespace CoreX\Search\Projection\Data;

use CoreX\Data\Data\RecordRef;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class RecordBatch
{
    public function __construct(public RecordRefs $records, public ?RecordRef $next) {}
}
