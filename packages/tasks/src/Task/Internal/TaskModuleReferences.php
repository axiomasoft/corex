<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ModuleTableReferences;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\ReferenceCursor;
use CoreX\Foundation\Data\ReferencePage;
use CoreX\Foundation\Laravel\SqlReferenceInventory;
use Illuminate\Database\DatabaseManager;
use Packman\Attributes\Internal;

#[Internal]
final readonly class TaskModuleReferences implements ModuleTableReferences
{
    public function __construct(private DatabaseManager $connections, private CurrentWorkScope $current) {}

    public function owner(): string
    {
        return 'axiomasoft/corex-tasks';
    }

    public function moduleTables(): array
    {
        return [
            'tsk_projects', 'tsk_stages', 'tsk_sprints', 'tsk_tasks', 'tsk_task_targets',
            'tsk_task_watchers', 'tsk_time_logs', 'tsk_timers', 'corex_task_operations',
        ];
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $tenant = $this->connections->connection();
        $this->current->assert($scope, $tenant);

        return SqlReferenceInventory::scan($this->owner(), [
            'tsk_projects' => [$tenant, []],
            'tsk_stages' => [$tenant, []],
            'tsk_sprints' => [$tenant, []],
            'tsk_tasks' => [$tenant, []],
            'tsk_task_targets' => [$tenant, ['entity_type']],
            'tsk_task_watchers' => [$tenant, []],
            'tsk_time_logs' => [$tenant, ['source']],
            'tsk_timers' => [$tenant, []],
            'corex_task_operations' => [$tenant, ['kind', 'result_ref']],
        ], $module, $scope, $after);
    }
}
