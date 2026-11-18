<?php

declare(strict_types=1);

namespace CoreX\Notifications\Inbox\Exceptions;

use CoreX\Notifications\Errors\Enums\NotificationsErrorCode;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class NotificationAccessDenied extends CodedException
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: NotificationsErrorCode::NotificationAccessDenied, category: ErrorCategory::Authorization, retryable: false);
    }
}
