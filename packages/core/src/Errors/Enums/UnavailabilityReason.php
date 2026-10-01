<?php

declare(strict_types=1);

namespace CoreX\Errors\Enums;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
enum UnavailabilityReason: string
{
    case CapabilityUnavailable = 'capability_unavailable';
    case CapabilityDisabled = 'capability_disabled';
    case OperationUnsupported = 'operation_unsupported';
    case AccessDenied = 'access_denied';
    case FeatureNotEntitled = 'feature_not_entitled';
}
