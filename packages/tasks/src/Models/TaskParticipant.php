<?php

declare(strict_types=1);

namespace CoreX\Tasks\Models;

use CoreX\Models\BaseModel;
use CoreX\Tasks\Enums\TaskParticipantRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $task_id
 * @property string $user_id
 * @property TaskParticipantRole $role
 */
final class TaskParticipant extends BaseModel
{
    protected $table = 'tsk_task_participants';

    /** @var list<string> */
    protected $fillable = ['task_id', 'user_id', 'role'];

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['role' => TaskParticipantRole::class];
    }
}
