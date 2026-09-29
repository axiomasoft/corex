<?php

declare(strict_types=1);

namespace CoreX\Panels;

use CoreX\Modules\Definition\ModuleDefinition;
use CoreX\Modules\Definition\ModuleManifest;
use Override;

final class PanelsModule extends ModuleDefinition
{
    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('axiomasoft/corex-panels')
            ->title('Panels')
            ->description('Headless panel descriptors and access catalogue.')
            ->tablePrefix('pnl');
    }
}
