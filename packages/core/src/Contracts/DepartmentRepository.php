<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\Departments\Department;
use CoreX\Events\DepartmentMoved;
use CoreX\Exceptions\DepartmentCycleException;

/**
 * Department hierarchy on `sys_departments` (ltree path, B-10 §3.1/§5.7).
 */
interface DepartmentRepository
{
    public function find(string $id): ?Department;

    /** @return list<Department> the department and everything under it (`path <@` includes the root) */
    public function subtree(string $departmentId): array;

    /** The user's primary department (AzGuard scope=dept, P2.9). */
    public function primaryOf(string $userId): ?Department;

    /**
     * Reparent a department (and its whole subtree) in one `UPDATE ... WHERE
     * path <@` — no recursive traversal. `$newParentId = null` moves it to
     * the workspace root. Dispatches {@see DepartmentMoved}.
     *
     * @throws DepartmentCycleException the new parent is inside the moving subtree
     */
    public function move(string $departmentId, ?string $newParentId): void;
}
