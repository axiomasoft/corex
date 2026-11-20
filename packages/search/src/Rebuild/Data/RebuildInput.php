<?php

declare(strict_types=1);

namespace CoreX\Search\Rebuild\Data;

use CoreX\Data\Data\EntityHandle;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class RebuildInput
{
    public function __construct(public EntityHandle $entity) {}
}
