<?php

declare(strict_types=1);

namespace CoreX\Notifications\Contracts;

use CoreX\Notifications\Data\CancelResult;
use CoreX\Notifications\Data\InboxQuery;
use CoreX\Notifications\Data\NotificationContext;
use CoreX\Notifications\Data\NotificationInput;
use CoreX\Notifications\Data\NotificationRef;

interface NotificationCenter
{
    public function notify(NotificationContext $context, NotificationInput $input): NotificationRef;

    public function cancel(NotificationContext $context, string $notificationId): CancelResult;

    public function markRead(NotificationContext $context, string $notificationId): void;

    /** @return list<NotificationRef> */
    public function inbox(NotificationContext $context, InboxQuery $query): array;

    public function unreadCount(NotificationContext $context): int;
}
