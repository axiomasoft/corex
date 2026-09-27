<?php

declare(strict_types=1);

namespace CoreX\Wakeup;

use CoreX\Modules\ModuleDefinition;
use CoreX\Modules\ModuleManifest;
use Override;

final class WakeupModule extends ModuleDefinition
{
    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('corex/wakeup')
            ->title('Wakeup')
            ->description('Durable local wakeup requests.')
            ->tablePrefix('sys');
    }
}
