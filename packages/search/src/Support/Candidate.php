<?php

declare(strict_types=1);

namespace CoreX\Search\Support;

use CoreX\Support\Data\RecordRef;

final readonly class Candidate
{
    public function __construct(public RecordRef $record) {}
}
