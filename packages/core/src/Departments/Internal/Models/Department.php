<?php

declare(strict_types=1);

namespace CoreX\Departments\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Department hierarchy row (`sys_departments`). `path` is ltree —
 * read/written as a plain string, PG casts on the wire.
 *
 * SoftDeletes (A24): a removed department must drop out of the scope that
 * feeds entity-scoped RBAC — the `deleted_at` column and the partial
 * index `WHERE deleted_at IS NULL` are in the migration for exactly this.
 * find()/subtree()/primaryOf() go through Eloquent and inherit the
 * SoftDeletingScope, so soft-deleted rows are excluded automatically.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string|null $parent_id
 * @property string $path
 * @property string $name
 * @property string|null $head_user_id
 * @property int $sort_order
 *
 * @internal spec: B-10 §2, P2.9
 */
final class Department extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'sys_departments';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // PG returns integers as strings over PDO; the DTO's `int $sortOrder`
        // is strict-typed, so cast here rather than at every read site.
        return ['sort_order' => 'integer'];
    }
}
