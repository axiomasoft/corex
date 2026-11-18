<?php

declare(strict_types=1);

namespace CoreX\Departments\Data;

use Packman\Attributes\Api;

/**
 * Read-model returned by `DepartmentRepository` implementations.
 */
#[Api(since: '1.0')]
final readonly class Department
{
    public function __construct(
        public string $id,
        public string $workspaceId,
        public ?string $parentId,
        public string $path,
        public string $name,
        public ?string $headUserId,
        public int $sortOrder = 0,
    ) {}
}
