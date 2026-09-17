<?php

declare(strict_types=1);

namespace CoreX\Tasks\Models;

use CoreX\Concerns\HasConfiguredId;
use CoreX\Tasks\Enums\SprintStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $project_id
 * @property string $name
 */
final class Sprint extends Model
{
    use HasConfiguredId;

    protected $table = 'tsk_sprints';

    /** @var list<string> */
    protected $fillable = ['project_id', 'name', 'goal', 'status', 'starts_on', 'ends_on'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'planned'];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
            'status' => SprintStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
