<?php

declare(strict_types=1);

namespace CoreX\Workflow;

use CoreX\Modules\ModuleDefinition;
use CoreX\Modules\ModuleManifest;
use Override;

final class WorkflowModule extends ModuleDefinition
{
    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('corex/workflow')
            ->title('Workflow')
            ->description('Optional durable workflow engine binding.')
            ->tablePrefix('wfl');
    }
}
