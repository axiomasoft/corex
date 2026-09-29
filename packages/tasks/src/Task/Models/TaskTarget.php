<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TaskTarget extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'tsk_task_targets';

    protected $primaryKey = null;

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = ['task_id', 'entity_type', 'entity_id'];

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
