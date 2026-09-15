<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs;

use CoreX\Modules\CorexModule;
use CoreX\Modules\Manifest;
use CoreX\Modules\SettingDefault;

/**
 * Minimal module used across P1.3–P1.6 tests: a title, a valid tablePrefix
 * and a single setting default — enough to exercise discovery/compilation.
 */
final class StubCorexModule extends CorexModule
{
    public function manifest(): Manifest
    {
        return Manifest::make('corex/stub-module')
            ->title('Stub Module')
            ->tablePrefix('stb')
            ->settings([
                SettingDefault::make('greeting', 'hello'),
            ]);
    }
}
