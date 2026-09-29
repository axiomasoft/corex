<?php

declare(strict_types=1);

namespace CoreX\Wakeup;

use CoreX\Modules\Definition\ModuleDefinition;
use CoreX\Modules\Definition\ModuleManifest;
use Override;

final class WakeupModule extends ModuleDefinition
{
    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('axiomasoft/corex-wakeup')
            ->title('Wakeup')
            ->description('Durable local wakeup requests.')
            ->tablePrefix('sys');
    }
}
