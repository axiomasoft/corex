<?php

declare(strict_types=1);

namespace CoreX\Tasks\Project\Internal\Models;

use CoreX\Database\Laravel\BaseModel;
use CoreX\Tasks\Project\Enums\ProjectStatus;
use CoreX\Tasks\Sprint\Laravel\SprintRelations;
use CoreX\Tasks\Task\Laravel\StageRelations;
use CoreX\Tasks\Task\Laravel\TaskRelations;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Packman\Attributes\Internal;

/**
 * @property string $id
 * @property string|null $workspace_id
 * @property string $name
 * @property ProjectStatus $status
 * @property DateTimeInterface|null $created_at
 * @property DateTimeInterface|null $updated_at
 */
#[Internal]
final class Project extends BaseModel
{
    protected $table = 'tsk_projects';

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'name', 'description', 'status', 'owner_id', 'starts_on', 'due_on', 'settings', 'created_by'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'active', 'settings' => '{}'];

    /** @return HasMany<covariant Model, $this> */
    public function stages(): HasMany
    {
        return StageRelations::hasMany($this);
    }

    /** @return HasMany<covariant Model, $this> */
    public function sprints(): HasMany
    {
        return SprintRelations::hasMany($this);
    }

    /** @return HasMany<covariant Model, $this> */
    public function tasks(): HasMany
    {
        return TaskRelations::hasMany($this);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => ProjectStatus::class, 'starts_on' => 'date', 'due_on' => 'date', 'settings' => 'array', 'created_by' => 'array'];
    }
}
