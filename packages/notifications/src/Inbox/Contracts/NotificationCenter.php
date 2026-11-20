<?php

declare(strict_types=1);

namespace CoreX\Notifications\Inbox\Contracts;

use CoreX\Notifications\Inbox\Data\CancelResult;
use CoreX\Notifications\Inbox\Data\InboxPage;
use CoreX\Notifications\Inbox\Data\InboxQuery;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Data\NotificationInput;
use CoreX\Notifications\Inbox\Data\NotificationRef;
use CoreX\Notifications\Inbox\Data\UnreadCount;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
interface NotificationCenter
{
    public function notify(NotificationContext $context, NotificationInput $input): NotificationRef;

    public function cancel(NotificationContext $context, string $notificationId): CancelResult;

    public function markRead(NotificationContext $context, string $notificationId): void;

    public function inbox(NotificationContext $context, InboxQuery $query): InboxPage;

    public function unreadCount(NotificationContext $context): UnreadCount;
}
