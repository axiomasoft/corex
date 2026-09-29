<?php

declare(strict_types=1);

namespace CoreX\Tasks\Project\Models;

use CoreX\Database\BaseModel;
use CoreX\Tasks\Project\Enums\ProjectStatus;
use CoreX\Tasks\Sprint\Models\Sprint;
use CoreX\Tasks\Task\Models\Stage;
use CoreX\Tasks\Task\Models\Task;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $workspace_id
 * @property string $name
 * @property ProjectStatus $status
 * @property DateTimeInterface|null $created_at
 * @property DateTimeInterface|null $updated_at
 */
final class Project extends BaseModel
{
    protected $table = 'tsk_projects';

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'name',
        'description',
        'status',
        'owner_id',
        'starts_on',
        'due_on',
        'settings',
        'created_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
        'settings' => '{}',
    ];

    /** @return HasMany<Stage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class);
    }

    /** @return HasMany<Sprint, $this> */
    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'starts_on' => 'date',
            'due_on' => 'date',
            'settings' => 'array',
            'created_by' => 'array',
        ];
    }
}
