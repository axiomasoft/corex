<?php

declare(strict_types=1);

namespace CoreX\Models;

use CoreX\Concerns\HasConfiguredId;
use CoreX\Support\Config;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * Department hierarchy row (sys_departments, B-10 §2). `path` is ltree —
 * read/written as a plain string, PG casts on the wire.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string|null $parent_id
 * @property string $path
 * @property string $name
 * @property string|null $head_user_id
 * @property int $sort_order
 */
final class Department extends Model
{
    use HasConfiguredId;

    protected $guarded = [];

    #[Override]
    public function getTable(): string
    {
        return Config::departmentsTable();
    }
}
