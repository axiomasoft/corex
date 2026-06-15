<?php

declare(strict_types=1);

namespace CoreX\Tasks\Actions;

use CoreX\Tasks\Enums\TaskParticipantRole;
use CoreX\Tasks\Models\Task;
use CoreX\Tasks\Models\TaskParticipant;
use Illuminate\Support\Facades\DB;

final class AddTaskParticipant
{
    public function handle(Task $task, string $userId, TaskParticipantRole $role = TaskParticipantRole::Responsible): TaskParticipant
    {
        return DB::transaction(fn (): TaskParticipant => TaskParticipant::query()->create([
            'task_id' => $task->id,
            'user_id' => $userId,
            'role' => $role,
        ]));
    }
}
