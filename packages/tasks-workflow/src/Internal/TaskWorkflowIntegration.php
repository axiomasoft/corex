<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Internal;

use CoreX\Modules\Definition\ModuleDefinition;
use CoreX\Modules\Definition\ModuleManifest;
use Override;

final class TaskWorkflowIntegration extends ModuleDefinition
{
    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('axiomasoft/corex-tasks-workflow')
            ->title('Tasks Workflow')
            ->description('Recurrence-to-task materialization with durable wakeup and optional workflow capture.')
            ->tablePrefix('twf');
    }
}
