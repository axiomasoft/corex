<?php

declare(strict_types=1);

namespace CoreX\Panels;

use CoreX\Modules\ModuleDefinition;
use CoreX\Modules\ModuleManifest;
use Override;

final class PanelsModule extends ModuleDefinition
{
    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('corex/panels')
            ->title('Panels')
            ->description('Headless panel descriptors and access catalogue.')
            ->tablePrefix('pnl');
    }
}
