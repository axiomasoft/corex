<?php

declare(strict_types=1);

namespace CoreX\Notifications\Inbox\Data;

enum NotificationAction: string
{
    case Notify = 'notify';
    case Read = 'read';
    case Cancel = 'cancel';
    case Preference = 'preference';
}
