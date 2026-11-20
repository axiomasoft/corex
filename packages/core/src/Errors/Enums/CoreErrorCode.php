<?php

declare(strict_types=1);

namespace CoreX\Errors\Enums;

use Packman\Attributes\Api;
use Packman\Errors\Contracts\ErrorCode;

#[Api(since: '1.0')]
enum CoreErrorCode: string implements ErrorCode
{
    case CentralContextException = 'core.central_context_exception';
    case CrossWorkspaceMoveException = 'core.cross_workspace_move_exception';
    case DepartmentCycleException = 'core.department_cycle_exception';
    case ImpersonationRestrictedException = 'core.impersonation_restricted_exception';
    case InboxRefused = 'core.inbox_refused';
    case InternalError = 'core.internal_error';
    case InvalidMessage = 'core.invalid_message';
    case InvalidScopePayload = 'core.invalid_scope_payload';
    case OutboxLeaseLost = 'core.outbox_lease_lost';
    case OutboxRefused = 'core.outbox_refused';
    case ScopeAuthorityUnavailable = 'core.scope_authority_unavailable';
    case ScopeStale = 'core.scope_stale';
    case ScopeContextMismatch = 'core.scope_context_mismatch';
    case SettingValueTooLargeException = 'core.setting_value_too_large_exception';
    case StorageCapabilityUnavailable = 'core.storage_capability_unavailable';
    case StorageOperationFailed = 'core.storage_operation_failed';
    case UnknownMessageContract = 'core.unknown_message_contract';
    case UnsupportedScopeException = 'core.unsupported_scope_exception';

    public function value(): string
    {
        return $this->value;
    }
}
