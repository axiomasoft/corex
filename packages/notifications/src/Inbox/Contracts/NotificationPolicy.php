<?php

declare(strict_types=1);

namespace CoreX\Notifications\Contracts;

use CoreX\Notifications\Data\NotificationAction;
use CoreX\Notifications\Data\NotificationContext;
use CoreX\Notifications\Data\NotificationSubject;

interface NotificationPolicy
{
    public function allows(NotificationContext $context, NotificationAction $action, NotificationSubject $subject): bool;
}
