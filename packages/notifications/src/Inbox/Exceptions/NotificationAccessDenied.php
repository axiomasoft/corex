<?php

declare(strict_types=1);

namespace CoreX\Notifications\Inbox\Exceptions;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;
use CoreX\Notifications\Errors\Enums\NotificationsErrorCode;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class NotificationAccessDenied extends CodedException
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: NotificationsErrorCode::NotificationAccessDenied, category: ErrorCategory::Authorization, retryable: false);
    }
}
