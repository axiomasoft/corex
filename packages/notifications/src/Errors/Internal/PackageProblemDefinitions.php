<?php

declare(strict_types=1);

namespace CoreX\Notifications\Errors\Internal;

use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Data\ProblemDefinition;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Notifications\Errors\Enums\NotificationsErrorCode;

/** @internal The owning package contributes its safe error catalogue at boot. */
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(code: NotificationsErrorCode::NotificationAccessDenied, type: 'urn:corex:problem:notifications.notification_access_denied', defaultStatus: 403, title: 'Notification access denied.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: NotificationsErrorCode::NotificationOperationConflict, type: 'urn:corex:problem:notifications.notification_operation_conflict', defaultStatus: 409, title: 'Notification operation conflict.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
    }
}
