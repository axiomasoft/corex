<?php

declare(strict_types=1);

namespace CoreX\Tasks\Sprint\Internal;

use CoreX\Tasks\Sprint\Data\SprintReference;
use CoreX\Tasks\Sprint\Internal\Models\Sprint;

/** @internal */
final class SprintReferenceFactory
{
    public static function fromSprint(Sprint $sprint): SprintReference
    {
        return new SprintReference(id: $sprint->id, type: SprintReference::TYPE, display: $sprint->name);
    }
}
