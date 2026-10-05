<?php

declare(strict_types=1);

namespace CoreX\Access\Matrix\Events;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class RoleMatrixCompiled
{
    public function __construct(public string $roleId) {}
}
