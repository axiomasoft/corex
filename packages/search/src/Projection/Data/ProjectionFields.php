<?php

declare(strict_types=1);

namespace CoreX\Search\Projection\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ProjectionFields
{
    /** @param list<ProjectionField> $fields */
    public function __construct(public array $fields, public string $revision)
    {
        ProjectionInput::objects($fields, ProjectionField::class, 256);
        ProjectionInput::digest($revision);

        if (count(array_unique(array_column($fields, 'id'))) !== count($fields)) {
            throw new InvalidArgumentException('A projection field ID has multiple owners.');
        }
    }
}
