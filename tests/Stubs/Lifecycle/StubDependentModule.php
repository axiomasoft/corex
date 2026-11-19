<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs\Lifecycle;

use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleDependency;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

/** Requires {@see StubBaseModule} — exercises AC-6/AC-8/cascade. */
final class StubDependentModule extends ModuleDefinition
{
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('corex/stub-dependent')
            ->title('Stub Dependent')
            ->tablePrefix('sbd')
            ->requires([ModuleDependency::module('corex/stub-base')]);
    }
}
