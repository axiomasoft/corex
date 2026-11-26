<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs\Lifecycle;

use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

/**
 * Migrations path has a second file that always throws
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
