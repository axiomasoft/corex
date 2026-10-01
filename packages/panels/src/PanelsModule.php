<?php

declare(strict_types=1);

namespace CoreX\Panels;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\ModuleDefinition;
use CoreX\Modules\Data\ModuleManifest;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
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
