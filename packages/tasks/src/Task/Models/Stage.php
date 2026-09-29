<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Models;

use CoreX\Database\HasConfiguredId;
use CoreX\Tasks\Project\Models\Project;
use CoreX\Tasks\Task\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $project_id
 * @property string $code
 * @property array<string, string> $name
 * @property string|null $color
 * @property TaskStatus|null $auto_status
 */
final class Stage extends Model
{
    use HasConfiguredId;

    protected $table = 'tsk_stages';

    /** @var list<string> */
    protected $fillable = ['project_id', 'code', 'name', 'color', 'position', 'auto_status'];

    /** @var array<string, mixed> */
    protected $attributes = ['position' => 0];

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
            'name' => 'array',
            'position' => 'integer',
            'auto_status' => TaskStatus::class,
        ];
    }
}
