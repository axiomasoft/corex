<?php

declare(strict_types=1);

namespace CoreX\Access\Roles\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class RoleView
{
    /** @param list<string> $assignableTo */
    public function __construct(public string $id, public string $slug, public array $assignableTo) {}
}
