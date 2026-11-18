<?php

declare(strict_types=1);

namespace CoreX\Notifications\Inbox\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
enum NotificationAction: string
{
    case Notify = 'notify';
    case Read = 'read';
    case Cancel = 'cancel';
    case Preference = 'preference';
}
