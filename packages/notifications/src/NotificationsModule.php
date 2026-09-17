<?php

declare(strict_types=1);

namespace CoreX\Notifications;

use CoreX\Modules\CorexModule;
use CoreX\Modules\Manifest;
use Override;

final class NotificationsModule extends CorexModule
{
    #[Override]
    public function manifest(): Manifest
    {
        return Manifest::make('corex/notifications')
            ->title('Notifications')
            ->description('Durable tenant-scoped notification primitives.')
            ->tablePrefix('ntf');
    }
}
