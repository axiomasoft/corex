<?php

declare(strict_types=1);

namespace CoreX\Notifications\Data;

enum NotificationAction: string
{
    case Notify = 'notify';
    case Read = 'read';
    case Cancel = 'cancel';
    case Preference = 'preference';
}
