<?php

declare(strict_types=1);

namespace CoreX\Access\Roles\Data;

use CoreX\Access\Enums\Scope;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class RoleMatrixEntry
{
    public function __construct(public string $entityKey, public string $action, public Scope $scope) {}
}
