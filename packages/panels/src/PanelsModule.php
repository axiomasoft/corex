<?php

declare(strict_types=1);

namespace CoreX\Panels;

use CoreX\Modules\CorexModule;
use CoreX\Modules\Manifest;
use Override;

final class PanelsModule extends CorexModule
{
    #[Override]
    public function manifest(): Manifest
    {
        return Manifest::make('corex/panels')
            ->title('Panels')
            ->description('Headless panel descriptors and access catalogue.')
            ->tablePrefix('pnl');
    }
}
