<?php

declare(strict_types=1);

namespace CoreX\Departments;

/**
 * Department read-DTO (B-10 §3.1 `DepartmentRepository`).
 */
final readonly class Department
{
    public function __construct(
        public string $id,
        public string $workspaceId,
        public ?string $parentId,
        public string $path,
        public string $name,
        public ?string $headUserId,
    ) {}
}
