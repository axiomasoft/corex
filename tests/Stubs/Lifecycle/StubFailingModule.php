<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs\Lifecycle;

use CoreX\Modules\CorexModule;
use CoreX\Modules\Manifest;

/**
 * Migrations path has a second file that always throws — exercises AC-7
 * (K-1 stays applied, mod_modules row not created, retry continues at K).
 */
final class StubFailingModule extends CorexModule
{
    public function manifest(): Manifest
    {
        return Manifest::make('corex/stub-failing')
            ->title('Stub Failing')
            ->tablePrefix('sbf');
    }
}
