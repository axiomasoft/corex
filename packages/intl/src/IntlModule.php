<?php

declare(strict_types=1);

namespace CoreX\Intl;

use CoreX\Modules\Contracts\ModuleDescriptor;

final class IntlModule
{
    public static function descriptor(): ModuleDescriptor
    {
        return new ModuleDescriptor(
            key: 'intl',
            label: 'Internationalization utilities',
            providers: [IntlServiceProvider::class],
        );
    }
}
