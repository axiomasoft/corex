<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

use CoreX\Data\Data\RecordRef;

final readonly class Candidate
{
    public function __construct(public RecordRef $record) {}
}
