<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Internal;

use Override;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

final class TaskWorkflowIntegration extends ModuleDefinition
{
    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('axiomasoft/corex-recurring-tasks')
            ->title('Tasks Workflow')
            ->description('Recurrence-to-task materialization with durable wakeup and optional workflow capture.')
            ->tablePrefix('twf');
    }
}
