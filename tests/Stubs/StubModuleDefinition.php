<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs;

use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Modules\Internal\Settings\DefaultSettingDefinition;

/**
 * Minimal module used across P1.3–P1.6 tests: a title, a valid tablePrefix
 * and a single setting default — enough to exercise discovery/compilation.
 */
final class StubModuleDefinition extends ModuleDefinition
{
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('corex/stub-module')
            ->title('Stub Module')
            ->tablePrefix('stb')
            ->settings([
                DefaultSettingDefinition::make('greeting', 'hello'),
            ]);
    }
}
