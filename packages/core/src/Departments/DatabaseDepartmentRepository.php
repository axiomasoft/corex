<?php

declare(strict_types=1);

namespace CoreX\Departments;

use CoreX\Contracts\DepartmentRepository;
use CoreX\Events\DepartmentMoved;
use CoreX\Exceptions\DepartmentCycleException;
use CoreX\Models\Department as DepartmentModel;
use CoreX\Support\Config;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * `sys_departments`/`sys_department_user` on ltree (B-10 §2/§3.1/§5.7).
 *
 * `$connection` mirrors DatabaseSettingsRepository's/DatabaseFeatureFlags'
 * seam (null = app default), used by the PG-only test lane (D12) — ltree/GIST
 * have no sqlite equivalent.
 */
final class DatabaseDepartmentRepository implements DepartmentRepository
{
    public function __construct(
        private readonly ?string $connection = null,
    ) {}

    #[Override]
    public function find(string $id): ?Department
    {
        $row = DepartmentModel::on($this->connection)->where('id', $id)->first();

        return $row instanceof DepartmentModel ? $this->toDto($row) : null;
    }

    #[Override]
    public function subtree(string $departmentId): array
    {
        $root = DepartmentModel::on($this->connection)->where('id', $departmentId)->first();

        if (! $root instanceof DepartmentModel) {
            return [];
        }

        /** @var list<DepartmentModel> $rows */
        $rows = DepartmentModel::on($this->connection)
            ->whereRaw('path <@ ?::ltree', [$root->path])
            ->orderBy('path')
            ->get()
            ->all();

        return array_map($this->toDto(...), $rows);
    }

    #[Override]
    public function primaryOf(string $userId): ?Department
    {
        $departmentId = $this->db()->table(Config::departmentUserTable())
            ->where('user_id', $userId)
            ->where('is_primary', true)
            ->value('department_id');

        return is_string($departmentId) ? $this->find($departmentId) : null;
    }

    #[Override]
    public function move(string $departmentId, ?string $newParentId): void
    {
        $this->db()->transaction(function () use ($departmentId, $newParentId): void {
            /** @var DepartmentModel $moving */
            $moving = DepartmentModel::on($this->connection)->where('id', $departmentId)->firstOrFail();

            // B-10 §7.3 п.3: xact-scoped, released on commit — no session-level lock.
            $this->db()->statement('select pg_advisory_xact_lock(hashtext(?))', ['sys_departments:'.$moving->workspace_id]);

            $newParentPath = '';

            if ($newParentId !== null) {
                /** @var DepartmentModel $newParent */
                $newParent = DepartmentModel::on($this->connection)->where('id', $newParentId)->firstOrFail();

                if ($newParent->path === $moving->path || str_starts_with($newParent->path, $moving->path.'.')) {
                    throw new DepartmentCycleException($departmentId, $newParentId);
                }

                $newParentPath = $newParent->path;
            }

            $oldPath = $moving->path;
            $ownLabel = str_contains($oldPath, '.') ? substr($oldPath, strrpos($oldPath, '.') + 1) : $oldPath;
            $newPath = $newParentPath !== '' ? $newParentPath.'.'.$ownLabel : $ownLabel;

            // ONE UPDATE over the whole subtree (`path <@`) — no recursive
            // per-row traversal (B-10 §5.7): strip the old-parent prefix
            // (nlevel(old moving path) - 1 labels) off every row under the
            // moving path and re-prepend the new parent's path.
            $this->db()->statement(
                'update '.Config::departmentsTable().' set
                    path = (?::ltree || subpath(path, nlevel(?::ltree) - 1)),
                    parent_id = case when id = ? then ? else parent_id end,
                    updated_at = now()
                where path <@ ?::ltree',
                [$newParentPath, $oldPath, $departmentId, $newParentId, $oldPath],
            );

            event(new DepartmentMoved(departmentId: $departmentId, oldPath: $oldPath, newPath: $newPath));
        });
    }

    private function toDto(DepartmentModel $row): Department
    {
        return new Department(
            id: $row->id,
            workspaceId: $row->workspace_id,
            parentId: $row->parent_id,
            path: $row->path,
            name: $row->name,
            headUserId: $row->head_user_id,
        );
    }

    private function db(): Connection
    {
        return DB::connection($this->connection);
    }
}
