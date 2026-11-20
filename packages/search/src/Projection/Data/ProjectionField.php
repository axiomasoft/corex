<?php

declare(strict_types=1);

namespace CoreX\Search\Projection\Data;

use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Enums\FieldUse;
use CoreX\Search\Projection\Enums\FieldType;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ProjectionField
{
    /** @param list<FieldUse> $uses */
    public function __construct(public string $id, public FieldType $type, public array $uses)
    {
        new FieldSelection([$id]);
        ProjectionInput::objects($uses, FieldUse::class, 7);
    }
}
