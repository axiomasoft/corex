<?php

declare(strict_types=1);

namespace CoreX\Media;

use CoreX\Modules\Definition\ModuleDefinition;
use CoreX\Modules\Definition\ModuleManifest;
use Override;

final class MediaModule extends ModuleDefinition
{
    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('axiomasoft/corex-media')
            ->title('Media')
            ->description('Tenant-scoped private media lifecycle primitives.')
            ->tablePrefix('med');
    }
}
