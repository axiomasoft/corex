<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Internal\Models;

use CoreX\Tasks\Project\Laravel\ProjectRelations;
use CoreX\Tasks\Task\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Packman\Attributes\Internal;

/**
 * @property bool $is_final
 * @property string $id
 * @property string $project_id
 * @property string $code
 * @property array<string, string> $name
 * @property string|null $color
 * @property TaskStatus|null $auto_status
 */
#[Internal]
final class Stage extends Model
{
    use HasUuids;

    protected $table = 'tsk_stages';

    /** @var list<string> */
    protected $fillable = ['project_id', 'code', 'name', 'color', 'position', 'auto_status'];

    /** @var array<string, mixed> */
    protected $attributes = ['position' => 0];

    /** @return BelongsTo<covariant Model, $this> */
    public function project(): BelongsTo
    {
        return ProjectRelations::belongsTo(model: $this, foreignKey: 'project_id', ownerKey: null, relation: 'project');
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['name' => 'array', 'position' => 'integer', 'auto_status' => TaskStatus::class];
    }
}
