<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Data;

use CoreX\Data\Data\FieldSelection;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class SearchSort
{
    public function __construct(public string $field, public bool $descending = false)
    {
        new FieldSelection([$field]);
    }
}
