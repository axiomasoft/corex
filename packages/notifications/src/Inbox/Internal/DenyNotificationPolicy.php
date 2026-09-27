<?php

declare(strict_types=1);

namespace CoreX\Notifications\Internal;

use CoreX\Notifications\Contracts\NotificationPolicy;
use CoreX\Notifications\Data\NotificationAction;
use CoreX\Notifications\Data\NotificationContext;
use CoreX\Notifications\Data\NotificationSubject;

final class DenyNotificationPolicy implements NotificationPolicy
{
    public function allows(NotificationContext $context, NotificationAction $action, NotificationSubject $subject): bool
    {
        return false;
    }
}
