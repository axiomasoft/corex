<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs\Lifecycle;

use CoreX\Modules\Definition\ModuleDefinition;
use CoreX\Modules\Definition\ModuleManifest;

/**
 * Migrations path has a second file that always throws — exercises AC-7
 * (K-1 stays applied, mod_modules row not created, retry continues at K).
 */
final class StubFailingModule extends ModuleDefinition
{
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('corex/stub-failing')
            ->title('Stub Failing')
            ->tablePrefix('sbf');
    }
}
