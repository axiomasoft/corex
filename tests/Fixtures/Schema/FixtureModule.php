<?php

declare(strict_types=1);

namespace CoreX\Tests\Fixtures\Schema;

use CoreX\Modules\Contracts\ModuleDefinition;
use CoreX\Modules\Data\ModuleManifest;

final class FixtureModule extends ModuleDefinition
{
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('fixture/module')->title('Schema fixture')->tablePrefix('scm');
    }
}
