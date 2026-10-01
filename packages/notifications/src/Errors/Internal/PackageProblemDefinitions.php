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
        yield new ProblemDefinition(NotificationsErrorCode::NotificationAccessDenied, 'urn:corex:problem:notifications.notification_access_denied', 403, 'Notification access denied.', false, ErrorCategory::Authorization, []);
        yield new ProblemDefinition(NotificationsErrorCode::NotificationOperationConflict, 'urn:corex:problem:notifications.notification_operation_conflict', 409, 'Notification operation conflict.', false, ErrorCategory::Conflict, []);
    }
}
