<?php

declare(strict_types=1);

namespace CoreX\Enums;

enum UnavailabilityReason: string
{
    case CapabilityUnavailable = 'capability_unavailable';
    case CapabilityDisabled = 'capability_disabled';
    case OperationUnsupported = 'operation_unsupported';
    case AccessDenied = 'access_denied';
    case FeatureNotEntitled = 'feature_not_entitled';
}
