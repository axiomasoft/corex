<?php

declare(strict_types=1);

namespace CoreX\Wakeup;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\ModuleDefinition;
use CoreX\Modules\Data\ModuleManifest;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
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
