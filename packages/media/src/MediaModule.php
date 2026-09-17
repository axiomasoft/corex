<?php

declare(strict_types=1);

namespace CoreX\Media;

use CoreX\Modules\CorexModule;
use CoreX\Modules\Manifest;
use Override;

final class MediaModule extends CorexModule
{
    #[Override]
    public function manifest(): Manifest
    {
        return Manifest::make('corex/media')
            ->title('Media')
            ->description('Tenant-scoped private media lifecycle primitives.')
            ->tablePrefix('med');
    }
}
