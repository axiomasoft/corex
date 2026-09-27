<?php

declare(strict_types=1);

namespace CoreX\Media;

use CoreX\Modules\ModuleDefinition;
use CoreX\Modules\ModuleManifest;
use Override;

final class MediaModule extends ModuleDefinition
{
    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('corex/media')
            ->title('Media')
            ->description('Tenant-scoped private media lifecycle primitives.')
            ->tablePrefix('med');
    }
}
