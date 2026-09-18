<?php

declare(strict_types=1);

namespace CoreX\Intl;

use CoreX\Modules\CorexModule;
use CoreX\Modules\Manifest;
use Override;

final class IntlModule extends CorexModule
{
    #[Override]
    public function manifest(): Manifest
    {
        return Manifest::make('corex/intl')
            ->title('Internationalization')
            ->description('Typed locale, money/rate snapshots, and offline geography reference utilities.')
            ->tablePrefix('int');
    }
}
