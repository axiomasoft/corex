<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Internal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Packman\Attributes\Internal;

#[Internal]
final class TaskWatcher extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'tsk_task_watchers';

    protected $primaryKey = null;

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = ['task_id', 'user_id'];

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
