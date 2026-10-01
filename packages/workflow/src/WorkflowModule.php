<?php

declare(strict_types=1);

namespace CoreX\Workflow;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\ModuleDefinition;
use CoreX\Modules\Data\ModuleManifest;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
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
