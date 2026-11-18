<?php

declare(strict_types=1);

namespace CoreX\Tasks\Sprint\Internal\Models;

use CoreX\Tasks\Project\Laravel\ProjectRelations;
use CoreX\Tasks\Sprint\Enums\SprintStatus;
use CoreX\Tasks\Task\Laravel\TaskRelations;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Packman\Attributes\Internal;

/**
 * @property string $id
 * @property string $project_id
 * @property string $name
 */
#[Internal]
final class Sprint extends Model
{
    use HasUuids;

    protected $table = 'tsk_sprints';

    /** @var list<string> */
    protected $fillable = ['project_id', 'name', 'goal', 'status', 'starts_on', 'ends_on'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'planned'];

    /** @return BelongsTo<covariant Model, $this> */
    public function project(): BelongsTo
    {
        return ProjectRelations::belongsTo(model: $this, foreignKey: 'project_id', ownerKey: null, relation: 'project');
    }

    /** @return HasMany<covariant Model, $this> */
    public function tasks(): HasMany
    {
        return TaskRelations::hasMany($this);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => SprintStatus::class, 'starts_on' => 'date', 'ends_on' => 'date'];
    }
}
