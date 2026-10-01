<?php

declare(strict_types=1);

namespace CoreX\Notifications\Inbox\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Notifications\Inbox\Data\NotificationAction;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Data\NotificationSubject;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface NotificationPolicy
{
    public function allows(NotificationContext $context, NotificationAction $action, NotificationSubject $subject): bool;
}
