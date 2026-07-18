<?php

declare(strict_types=1);

namespace CoreX\Tasks\Models;

use CoreX\Models\BaseModel;
use CoreX\Tasks\Enums\TaskPriority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $process_id
 * @property string $stage_id
 * @property string|null $project_id
 * @property string $task_key
 * @property string $title
 * @property array<string, mixed>|null $description_json
 * @property TaskPriority $priority
 * @property string $creator_id
 * @property string|null $responsible_id
 * @property int $sort_order
 * @property Carbon|null $due_date
 * @property int|null $estimated_minutes
 * @property int $spent_minutes
 * @property string|null $parent_id
 * @property string|null $taskable_type
 * @property string|null $taskable_id
 * @property Carbon|null $archived_at
 */
final class Task extends BaseModel
{
    protected $table = 'tsk_tasks';

    /** @var list<string> */
    protected $fillable = [
        'process_id', 'stage_id', 'project_id', 'task_key', 'title', 'description_json', 'priority',
        'creator_id', 'responsible_id', 'sort_order', 'due_date', 'estimated_minutes', 'spent_minutes', 'parent_id', 'archived_at',
    ];

    /** @return BelongsTo<Process, $this> */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Task, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<TaskParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(TaskParticipant::class);
    }

    /** @return MorphTo<Model, $this> */
    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'description_json' => 'array',
            'priority' => TaskPriority::class,
            'sort_order' => 'integer',
            'due_date' => 'datetime',
            'estimated_minutes' => 'integer',
            'spent_minutes' => 'integer',
            'archived_at' => 'datetime',
        ];
    }
}
