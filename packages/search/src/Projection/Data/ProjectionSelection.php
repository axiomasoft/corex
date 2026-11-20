<?php

declare(strict_types=1);

namespace CoreX\Search\Projection\Data;

use CoreX\Data\Data\FieldSelection;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ProjectionSelection
{
    /** @param list<string> $fields */
    public function __construct(public array $fields)
    {
        new FieldSelection($fields);
    }
}
