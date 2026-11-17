<?php

declare(strict_types=1);

namespace CoreX\Intl;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Override;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class IntlModule extends ModuleDefinition
{
    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('axiomasoft/corex-intl')
            ->title('Internationalization')
            ->description('Typed locale, money/rate snapshots, and offline geography reference utilities.')
            ->tablePrefix('int');
    }
}
