<?php

declare(strict_types=1);

namespace CoreX\Notifications\Errors\Internal;

use CoreX\Notifications\Errors\Enums\NotificationsErrorCode;
use Packman\Attributes\Internal;
use Packman\Errors\Contracts\ProblemDefinitionProvider;
use Packman\Errors\Data\ProblemDefinition;
use Packman\Errors\Enums\ErrorCategory;

/** @internal The owning package contributes its safe error catalogue at boot. */
#[Internal]
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(code: NotificationsErrorCode::NotificationAccessDenied, type: 'urn:packman:problem:notifications.notification_access_denied', defaultStatus: 403, title: 'Notification access denied.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: NotificationsErrorCode::NotificationOperationConflict, type: 'urn:packman:problem:notifications.notification_operation_conflict', defaultStatus: 409, title: 'Notification operation conflict.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
    }
}
