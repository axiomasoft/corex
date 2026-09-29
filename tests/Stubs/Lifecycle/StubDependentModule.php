<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs\Lifecycle;

use CoreX\Modules\Definition\ModuleDefinition;
use CoreX\Modules\Definition\ModuleManifest;
use CoreX\Modules\Internal\Definition\ModuleDependency;

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
