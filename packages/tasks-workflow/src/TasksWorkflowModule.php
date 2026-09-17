<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow;

use CoreX\Modules\CorexModule;
use CoreX\Modules\Manifest;
use Override;

final class TasksWorkflowModule extends CorexModule
{
    #[Override]
    public function manifest(): Manifest
    {
        return Manifest::make('corex/tasks-workflow')
            ->title('Tasks Workflow')
            ->description('Recurrence-to-task materialization with durable wakeup and optional workflow capture.')
            ->tablePrefix('twf');
    }
}
