<?php

declare(strict_types=1);

namespace CoreX\Search\Projection\Data;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ProjectionContext
{
    public function __construct(public DataScope $scope, public EntityHandle $entity) {}
}
