<?php

declare(strict_types=1);

namespace CoreX\Tasks\Actions;

use CoreX\Tasks\Events\TaskMovedToStage;
use CoreX\Tasks\Models\Stage;
use CoreX\Tasks\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * Moves a task to a stage; reaching a final stage archives the task, leaving it
 * otherwise active.
 */
final class MoveTaskToStage
{
    public function handle(Task $task, Stage $stage): Task
    {
        return DB::transaction(function () use ($task, $stage): Task {
            $task->stage()->associate($stage);
            $task->archived_at = $stage->is_final ? now() : null;
            $task->save();

            TaskMovedToStage::dispatch($task, $stage);

            return $task;
        });
    }
}
