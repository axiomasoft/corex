<?php

declare(strict_types=1);

namespace CoreX\Tasks\Actions;

use CoreX\Tasks\Enums\TaskPriority;
use CoreX\Tasks\Events\TaskCreated;
use CoreX\Tasks\Models\Process;
use CoreX\Tasks\Models\Stage;
use CoreX\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates a task at the process's first stage (or the given stage). The
 * transaction is the use-case boundary; tenant_id is auto-stamped (ADR-004).
 */
final class CreateTask
{
    public function handle(
        Process $process,
        string $title,
        string $creatorId,
        ?Stage $stage = null,
        ?string $responsibleId = null,
        ?string $projectId = null,
        TaskPriority $priority = TaskPriority::Medium,
        ?Model $taskable = null,
    ): Task {
        return DB::transaction(function () use ($process, $title, $creatorId, $stage, $responsibleId, $projectId, $priority, $taskable): Task {
            $stage ??= $process->stages()->first();

            if ($stage === null) {
                throw new RuntimeException('Процесс без стадий: добавьте стадию перед созданием задачи.');
            }

            $task = new Task([
                'process_id' => $process->id,
                'stage_id' => $stage->id,
                'project_id' => $projectId,
                'task_key' => 'T-'.Str::upper(Str::random(8)),
                'title' => $title,
                'priority' => $priority,
                'creator_id' => $creatorId,
                'responsible_id' => $responsibleId,
            ]);

            if ($taskable instanceof Model) {
                $task->taskable()->associate($taskable);
            }

            $task->save();

            TaskCreated::dispatch($task);

            return $task;
        });
    }
}
