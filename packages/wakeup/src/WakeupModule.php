<?php

declare(strict_types=1);

namespace CoreX\Wakeup;

use CoreX\Modules\CorexModule;
use CoreX\Modules\Manifest;
use Override;

final class WakeupModule extends CorexModule
{
    #[Override]
    public function manifest(): Manifest
    {
        return Manifest::make('corex/wakeup')
            ->title('Wakeup')
            ->description('Durable local wakeup requests.')
            ->tablePrefix('sys');
    }
}
