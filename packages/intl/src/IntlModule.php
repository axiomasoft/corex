<?php

declare(strict_types=1);

namespace CoreX\Intl;

use CoreX\Modules\ModuleDefinition;
use CoreX\Modules\ModuleManifest;
use Override;

final class IntlModule extends ModuleDefinition
{
    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('corex/intl')
            ->title('Internationalization')
            ->description('Typed locale, money/rate snapshots, and offline geography reference utilities.')
            ->tablePrefix('int');
    }
}
