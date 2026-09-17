<?php

declare(strict_types=1);

namespace CoreX\Workflow;

use CoreX\Modules\CorexModule;
use CoreX\Modules\Manifest;
use Override;

final class WorkflowModule extends CorexModule
{
    #[Override]
    public function manifest(): Manifest
    {
        return Manifest::make('corex/workflow')
            ->title('Workflow')
            ->description('Optional durable workflow engine binding.')
            ->tablePrefix('wfl');
    }
}
