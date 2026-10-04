<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Internal;

use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Data\ProblemDefinition;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;

/** @internal The owning package contributes its safe error catalogue at boot. */
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(OperationsErrorCode::BulkNotReady, 'urn:corex:problem:operations.bulk_not_ready', 409, 'Bulk result is not ready.', true, ErrorCategory::Conflict);
        yield new ProblemDefinition(OperationsErrorCode::BulkReconciliationRequired, 'urn:corex:problem:operations.bulk_reconciliation_required', 409, 'Bulk reconciliation is required.', false, ErrorCategory::Conflict);
        yield new ProblemDefinition(OperationsErrorCode::BulkResultExpired, 'urn:corex:problem:operations.bulk_result_expired', 410, 'Bulk result has expired.', false, ErrorCategory::NotFound);
        yield new ProblemDefinition(OperationsErrorCode::AccessDenied, 'urn:corex:problem:operations.access_denied', 403, 'Access denied.', false, ErrorCategory::Authorization, []);
        yield new ProblemDefinition(OperationsErrorCode::AccountUnavailable, 'urn:corex:problem:operations.account_unavailable', 403, 'Account unavailable.', false, ErrorCategory::Authorization, []);
        yield new ProblemDefinition(OperationsErrorCode::AuthenticationRequired, 'urn:corex:problem:operations.authentication_required', 401, 'Authentication required.', false, ErrorCategory::Authentication, []);
        yield new ProblemDefinition(OperationsErrorCode::BudgetRateLimited, 'urn:corex:problem:operations.budget_rate_limited', 429, 'Budget rate limited.', false, ErrorCategory::RateLimit, []);
        yield new ProblemDefinition(OperationsErrorCode::BudgetStoreUnavailable, 'urn:corex:problem:operations.budget_store_unavailable', 500, 'Budget store unavailable.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(OperationsErrorCode::CapabilityUnavailable, 'urn:corex:problem:operations.capability_unavailable', 503, 'Capability unavailable.', false, ErrorCategory::Unavailable, ['reason']);
        yield new ProblemDefinition(OperationsErrorCode::CatalogueLimit, 'urn:corex:problem:operations.catalogue_limit', 403, 'Catalogue limit.', false, ErrorCategory::Authorization, []);
        yield new ProblemDefinition(OperationsErrorCode::Conflict, 'urn:corex:problem:operations.conflict', 409, 'Conflict.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(OperationsErrorCode::ContextRequired, 'urn:corex:problem:operations.context_required', 403, 'Context required.', false, ErrorCategory::Authorization, []);
        yield new ProblemDefinition(OperationsErrorCode::CursorInvalid, 'urn:corex:problem:operations.cursor_invalid', 422, 'Cursor invalid.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(OperationsErrorCode::Forbidden, 'urn:corex:problem:operations.forbidden', 403, 'Forbidden.', false, ErrorCategory::Authorization, []);
        yield new ProblemDefinition(OperationsErrorCode::IdempotencyConflict, 'urn:corex:problem:operations.idempotency_conflict', 409, 'Idempotency conflict.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(OperationsErrorCode::IdempotencyContractMismatch, 'urn:corex:problem:operations.idempotency_contract_mismatch', 409, 'Idempotency contract mismatch.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(OperationsErrorCode::IdempotencyInProgress, 'urn:corex:problem:operations.idempotency_in_progress', 409, 'Idempotency in progress.', true, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(OperationsErrorCode::IdempotencyKeyForbidden, 'urn:corex:problem:operations.idempotency_key_forbidden', 422, 'Idempotency key forbidden.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(OperationsErrorCode::IdempotencyKeyInvalid, 'urn:corex:problem:operations.idempotency_key_invalid', 422, 'Idempotency key invalid.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(OperationsErrorCode::IdempotencyKeyRequired, 'urn:corex:problem:operations.idempotency_key_required', 422, 'Idempotency key required.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(OperationsErrorCode::IdempotencyNotSupported, 'urn:corex:problem:operations.idempotency_not_supported', 422, 'Idempotency not supported.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(OperationsErrorCode::InvalidRequest, 'urn:corex:problem:operations.invalid_request', 422, 'Invalid request.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(OperationsErrorCode::ModuleUnavailable, 'urn:corex:problem:operations.module_unavailable', 404, 'Module unavailable.', false, ErrorCategory::NotFound, []);
        yield new ProblemDefinition(OperationsErrorCode::NotFound, 'urn:corex:problem:operations.not_found', 404, 'Not found.', false, ErrorCategory::NotFound, []);
        yield new ProblemDefinition(OperationsErrorCode::OperationNotFound, 'urn:corex:problem:operations.operation_not_found', 404, 'Operation not found.', false, ErrorCategory::NotFound, []);
        yield new ProblemDefinition(OperationsErrorCode::OperationUnsupported, 'urn:corex:problem:operations.operation_unsupported', 404, 'Operation unsupported.', false, ErrorCategory::NotFound, []);
        yield new ProblemDefinition(OperationsErrorCode::OrderingVersionUnsupported, 'urn:corex:problem:operations.ordering_version_unsupported', 422, 'Ordering version unsupported.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(OperationsErrorCode::RateLimited, 'urn:corex:problem:operations.rate_limited', 429, 'Rate limited.', false, ErrorCategory::RateLimit, []);
        yield new ProblemDefinition(OperationsErrorCode::ReconciliationRequired, 'urn:corex:problem:operations.reconciliation_required', 409, 'Reconciliation required.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(OperationsErrorCode::RequestRejected, 'urn:corex:problem:operations.request_rejected', 422, 'Request rejected.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(OperationsErrorCode::ResourceNotFound, 'urn:corex:problem:operations.resource_not_found', 404, 'Resource not found.', false, ErrorCategory::NotFound, []);
        yield new ProblemDefinition(OperationsErrorCode::ScopeRequired, 'urn:corex:problem:operations.scope_required', 403, 'Scope required.', false, ErrorCategory::Authorization, []);
        yield new ProblemDefinition(OperationsErrorCode::UnknownMessageContract, 'urn:corex:problem:operations.unknown_message_contract', 422, 'Unknown message contract.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(OperationsErrorCode::InputEnvelopeInvalid, 'urn:corex:problem:operations.input_envelope_invalid', 422, 'Input envelope is invalid.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(OperationsErrorCode::UndeclaredFields, 'urn:corex:problem:operations.undeclared_fields', 422, 'Input contains undeclared fields.', false, ErrorCategory::Validation, ['fields']);
        yield new ProblemDefinition(OperationsErrorCode::UnsupportedMediaType, 'urn:corex:problem:operations.unsupported_media_type', 415, 'Unsupported media type.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(OperationsErrorCode::ValidationFailed, 'urn:corex:problem:operations.validation_failed', 422, 'Validation failed.', false, ErrorCategory::Validation, ['*']);
    }
}
