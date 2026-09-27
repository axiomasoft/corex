<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow;

use CoreX\Modules\ModuleDefinition;
use CoreX\Modules\ModuleManifest;
use Override;

final class TaskWorkflowIntegration extends ModuleDefinition
{
    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('corex/tasks-workflow')
            ->title('Tasks Workflow')
            ->description('Recurrence-to-task materialization with durable wakeup and optional workflow capture.')
            ->tablePrefix('twf');
    }
}
