<?php

declare(strict_types=1);

namespace CoreX\Workflow;

use CoreX\Modules\Definition\ModuleDefinition;
use CoreX\Modules\Definition\ModuleManifest;
use Override;

final class WorkflowModule extends ModuleDefinition
{
    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('axiomasoft/corex-workflow')
            ->title('Workflow')
            ->description('Optional durable workflow engine binding.')
            ->tablePrefix('wfl');
    }
}
