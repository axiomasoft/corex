<?php

declare(strict_types=1);

namespace CoreX\Notifications;

use CoreX\Modules\ModuleDefinition;
use CoreX\Modules\ModuleManifest;
use Override;

final class NotificationsModule extends ModuleDefinition
{
    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('corex/notifications')
            ->title('Notifications')
            ->description('Durable tenant-scoped notification primitives.')
            ->tablePrefix('ntf');
    }
}
