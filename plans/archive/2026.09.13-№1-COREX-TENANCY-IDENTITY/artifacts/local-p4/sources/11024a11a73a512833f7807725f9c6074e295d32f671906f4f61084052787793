<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs\Lifecycle;

use CoreX\Modules\CorexModule;
use CoreX\Modules\Dep;
use CoreX\Modules\Manifest;

/** Requires {@see StubBaseModule} — exercises AC-6/AC-8/cascade. */
final class StubDependentModule extends CorexModule
{
    public function manifest(): Manifest
    {
        return Manifest::make('corex/stub-dependent')
            ->title('Stub Dependent')
            ->tablePrefix('sbd')
            ->requires([Dep::module('corex/stub-base')]);
    }
}
