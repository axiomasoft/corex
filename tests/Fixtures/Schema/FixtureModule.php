<?php

declare(strict_types=1);

namespace CoreX\Tests\Fixtures\Schema;

use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

final class FixtureModule extends ModuleDefinition
{
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('fixture/module')->title('Schema fixture')->tablePrefix('scm');
    }
}
