<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

use CoreX\Data\Data\RecordRef;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class Candidate
{
    public function __construct(public RecordRef $record) {}
}
