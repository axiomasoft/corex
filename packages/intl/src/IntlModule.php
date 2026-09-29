<?php

declare(strict_types=1);

namespace CoreX\Intl;

use CoreX\Modules\Definition\ModuleDefinition;
use CoreX\Modules\Definition\ModuleManifest;
use Override;

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
