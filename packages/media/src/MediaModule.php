<?php

declare(strict_types=1);

namespace CoreX\Media;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Override;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

#[Spi(kind: SpiKind::Call, since: '1.0')]
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
