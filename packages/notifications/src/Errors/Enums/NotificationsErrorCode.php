<?php

declare(strict_types=1);

namespace CoreX\Notifications\Errors\Enums;

use Packman\Attributes\Api;
use Packman\Errors\Contracts\ErrorCode;

#[Api(since: '1.0')]
enum NotificationsErrorCode: string implements ErrorCode
{
    case NotificationAccessDenied = 'notifications.notification_access_denied';
    case NotificationOperationConflict = 'notifications.notification_operation_conflict';

    public function value(): string
    {
        return $this->value;
    }
}
