<?php

declare(strict_types=1);

namespace ThirdParty\Search;

use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

final class RegisteredModule extends ModuleDefinition
{
    public function manifest(): ModuleManifest
    {
        return Module::manifest();
    }
}
