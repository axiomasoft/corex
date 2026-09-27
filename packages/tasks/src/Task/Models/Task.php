<?php

declare(strict_types=1);

namespace CoreX\Tasks\Models;

use CoreX\Models\BaseModel;
use CoreX\Tasks\Enums\TaskPriority;
use CoreX\Tasks\Enums\TaskStatus;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $workspace_id
 * @property string|null $project_id
 * @property string|null $sprint_id
 * @property string|null $stage_id
 * @property string|null $parent_id
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property TaskPriority $priority
 * @property string|null $author_id
 * @property string|null $assignee_id
 * @property DateTimeInterface|null $starts_at
 * @property DateTimeInterface|null $due_at
 * @property DateTimeInterface|null $completed_at
 * @property int|null $estimate_minutes
 * @property int $spent_minutes
 * @property string $position
 * @property DateTimeInterface|null $created_at
 * @property DateTimeInterface|null $updated_at
 * @property-read Project|null $project
 * @property-read Stage|null $stage
 */
final class Task extends BaseModel
{
    protected $table = 'tsk_tasks';

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'project_id',
        'sprint_id',
        'stage_id',
        'parent_id',
        'title',
        'description',
        'status',
        'priority',
        'author_id',
        'assignee_id',
        'created_by',
        'starts_at',
        'due_at',
        'completed_at',
        'requires_acceptance',
        'recurrence_rule_id',
        'recurrence_occurs_at',
        'estimate_minutes',
        'spent_minutes',
        'position',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'open',
        'priority' => 'normal',
        'requires_acceptance' => false,
        'spent_minutes' => 0,
        'position' => 0,
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Sprint, $this> */
    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
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

    /** @return HasMany<TaskTarget, $this> */
    public function targets(): HasMany
    {
        return $this->hasMany(TaskTarget::class);
    }

    /** @return HasMany<TaskWatcher, $this> */
    public function watchers(): HasMany
    {
        return $this->hasMany(TaskWatcher::class);
    }

    /** @return HasMany<TimeLog, $this> */
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'created_by' => 'array',
            'starts_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'requires_acceptance' => 'boolean',
            'recurrence_occurs_at' => 'datetime',
            'estimate_minutes' => 'integer',
            'spent_minutes' => 'integer',
            'position' => 'decimal:10',
        ];
    }
}
