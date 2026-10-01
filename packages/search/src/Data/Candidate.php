<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

use CoreX\Attributes\Api;
use CoreX\Data\Data\RecordRef;

#[Api(since: '1.0')]
final readonly class Candidate
{
    public function __construct(public RecordRef $record) {}
}
