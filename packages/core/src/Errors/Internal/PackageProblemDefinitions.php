<?php

declare(strict_types=1);

namespace CoreX\Errors\Internal;

use CoreX\Errors\Enums\CoreErrorCode;
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

        yield new ProblemDefinition(code: CoreErrorCode::CentralContextException, type: 'urn:packman:problem:core.central_context_exception', defaultStatus: 403, title: 'Central context exception.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::CrossWorkspaceMoveException, type: 'urn:packman:problem:core.cross_workspace_move_exception', defaultStatus: 409, title: 'Cross workspace move exception.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::DepartmentCycleException, type: 'urn:packman:problem:core.department_cycle_exception', defaultStatus: 409, title: 'Department cycle exception.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::ImpersonationRestrictedException, type: 'urn:packman:problem:core.impersonation_restricted_exception', defaultStatus: 403, title: 'Impersonation restricted exception.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::InboxRefused, type: 'urn:packman:problem:core.inbox_refused', defaultStatus: 409, title: 'Inbox refused.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::InternalError, type: 'urn:packman:problem:core.internal_error', defaultStatus: 500, title: 'Internal error.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::InvalidMessage, type: 'urn:packman:problem:core.invalid_message', defaultStatus: 422, title: 'Invalid message.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::InvalidScopePayload, type: 'urn:packman:problem:core.invalid_scope_payload', defaultStatus: 422, title: 'Invalid scope payload.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::OutboxLeaseLost, type: 'urn:packman:problem:core.outbox_lease_lost', defaultStatus: 409, title: 'Outbox lease lost.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::OutboxRefused, type: 'urn:packman:problem:core.outbox_refused', defaultStatus: 409, title: 'Outbox refused.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::ScopeAuthorityUnavailable, type: 'urn:packman:problem:core.scope_authority_unavailable', defaultStatus: 503, title: 'Work scope authority is unavailable.', retryable: true, category: ErrorCategory::Unavailable, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::ScopeStale, type: 'urn:packman:problem:core.scope_stale', defaultStatus: 409, title: 'Work scope is stale.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::ScopeContextMismatch, type: 'urn:packman:problem:core.scope_context_mismatch', defaultStatus: 403, title: 'Scope context mismatch.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::SettingValueTooLargeException, type: 'urn:packman:problem:core.setting_value_too_large_exception', defaultStatus: 422, title: 'Setting value too large exception.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::StorageCapabilityUnavailable, type: 'urn:packman:problem:core.storage_capability_unavailable', defaultStatus: 503, title: 'Storage capability unavailable.', retryable: false, category: ErrorCategory::Unavailable, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::StorageOperationFailed, type: 'urn:packman:problem:core.storage_operation_failed', defaultStatus: 503, title: 'Storage operation failed.', retryable: true, category: ErrorCategory::Unavailable, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::UnknownMessageContract, type: 'urn:packman:problem:core.unknown_message_contract', defaultStatus: 422, title: 'Unknown message contract.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::UnsupportedScopeException, type: 'urn:packman:problem:core.unsupported_scope_exception', defaultStatus: 422, title: 'Unsupported scope exception.', retryable: false, category: ErrorCategory::Unsupported, detailKeys: []);
    }
}
