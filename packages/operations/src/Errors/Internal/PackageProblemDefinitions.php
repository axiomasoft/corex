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
        yield new ProblemDefinition(code: OperationsErrorCode::BulkNotReady, type: 'urn:corex:problem:operations.bulk_not_ready', defaultStatus: 409, title: 'Bulk result is not ready.', retryable: true, category: ErrorCategory::Conflict);
        yield new ProblemDefinition(code: OperationsErrorCode::BulkReconciliationRequired, type: 'urn:corex:problem:operations.bulk_reconciliation_required', defaultStatus: 409, title: 'Bulk reconciliation is required.', retryable: false, category: ErrorCategory::Conflict);
        yield new ProblemDefinition(code: OperationsErrorCode::BulkResultExpired, type: 'urn:corex:problem:operations.bulk_result_expired', defaultStatus: 410, title: 'Bulk result has expired.', retryable: false, category: ErrorCategory::NotFound);
        yield new ProblemDefinition(code: OperationsErrorCode::AccessDenied, type: 'urn:corex:problem:operations.access_denied', defaultStatus: 403, title: 'Access denied.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::AccountUnavailable, type: 'urn:corex:problem:operations.account_unavailable', defaultStatus: 403, title: 'Account unavailable.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::AuthenticationRequired, type: 'urn:corex:problem:operations.authentication_required', defaultStatus: 401, title: 'Authentication required.', retryable: false, category: ErrorCategory::Authentication, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::BudgetRateLimited, type: 'urn:corex:problem:operations.budget_rate_limited', defaultStatus: 429, title: 'Budget rate limited.', retryable: false, category: ErrorCategory::RateLimit, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::BudgetStoreUnavailable, type: 'urn:corex:problem:operations.budget_store_unavailable', defaultStatus: 500, title: 'Budget store unavailable.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::CapabilityUnavailable, type: 'urn:corex:problem:operations.capability_unavailable', defaultStatus: 503, title: 'Capability unavailable.', retryable: false, category: ErrorCategory::Unavailable, detailKeys: ['reason']);
        yield new ProblemDefinition(code: OperationsErrorCode::CatalogueLimit, type: 'urn:corex:problem:operations.catalogue_limit', defaultStatus: 403, title: 'Catalogue limit.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::Conflict, type: 'urn:corex:problem:operations.conflict', defaultStatus: 409, title: 'Conflict.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::ContextRequired, type: 'urn:corex:problem:operations.context_required', defaultStatus: 403, title: 'Context required.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::CursorInvalid, type: 'urn:corex:problem:operations.cursor_invalid', defaultStatus: 422, title: 'Cursor invalid.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::Forbidden, type: 'urn:corex:problem:operations.forbidden', defaultStatus: 403, title: 'Forbidden.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::IdempotencyConflict, type: 'urn:corex:problem:operations.idempotency_conflict', defaultStatus: 409, title: 'Idempotency conflict.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::IdempotencyContractMismatch, type: 'urn:corex:problem:operations.idempotency_contract_mismatch', defaultStatus: 409, title: 'Idempotency contract mismatch.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::IdempotencyInProgress, type: 'urn:corex:problem:operations.idempotency_in_progress', defaultStatus: 409, title: 'Idempotency in progress.', retryable: true, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::IdempotencyKeyForbidden, type: 'urn:corex:problem:operations.idempotency_key_forbidden', defaultStatus: 422, title: 'Idempotency key forbidden.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::IdempotencyKeyInvalid, type: 'urn:corex:problem:operations.idempotency_key_invalid', defaultStatus: 422, title: 'Idempotency key invalid.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::IdempotencyKeyRequired, type: 'urn:corex:problem:operations.idempotency_key_required', defaultStatus: 422, title: 'Idempotency key required.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::IdempotencyNotSupported, type: 'urn:corex:problem:operations.idempotency_not_supported', defaultStatus: 422, title: 'Idempotency not supported.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::InvalidRequest, type: 'urn:corex:problem:operations.invalid_request', defaultStatus: 422, title: 'Invalid request.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::ModuleUnavailable, type: 'urn:corex:problem:operations.module_unavailable', defaultStatus: 404, title: 'Module unavailable.', retryable: false, category: ErrorCategory::NotFound, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::NotFound, type: 'urn:corex:problem:operations.not_found', defaultStatus: 404, title: 'Not found.', retryable: false, category: ErrorCategory::NotFound, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::OperationNotFound, type: 'urn:corex:problem:operations.operation_not_found', defaultStatus: 404, title: 'Operation not found.', retryable: false, category: ErrorCategory::NotFound, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::OperationUnsupported, type: 'urn:corex:problem:operations.operation_unsupported', defaultStatus: 404, title: 'Operation unsupported.', retryable: false, category: ErrorCategory::NotFound, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::OrderingVersionUnsupported, type: 'urn:corex:problem:operations.ordering_version_unsupported', defaultStatus: 422, title: 'Ordering version unsupported.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::RateLimited, type: 'urn:corex:problem:operations.rate_limited', defaultStatus: 429, title: 'Rate limited.', retryable: false, category: ErrorCategory::RateLimit, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::ReconciliationRequired, type: 'urn:corex:problem:operations.reconciliation_required', defaultStatus: 409, title: 'Reconciliation required.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::RequestRejected, type: 'urn:corex:problem:operations.request_rejected', defaultStatus: 422, title: 'Request rejected.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::ResourceNotFound, type: 'urn:corex:problem:operations.resource_not_found', defaultStatus: 404, title: 'Resource not found.', retryable: false, category: ErrorCategory::NotFound, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::ScopeRequired, type: 'urn:corex:problem:operations.scope_required', defaultStatus: 403, title: 'Scope required.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::UnknownMessageContract, type: 'urn:corex:problem:operations.unknown_message_contract', defaultStatus: 422, title: 'Unknown message contract.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::InputEnvelopeInvalid, type: 'urn:corex:problem:operations.input_envelope_invalid', defaultStatus: 422, title: 'Input envelope is invalid.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::UndeclaredFields, type: 'urn:corex:problem:operations.undeclared_fields', defaultStatus: 422, title: 'Input contains undeclared fields.', retryable: false, category: ErrorCategory::Validation, detailKeys: ['fields']);
        yield new ProblemDefinition(code: OperationsErrorCode::UnsupportedMediaType, type: 'urn:corex:problem:operations.unsupported_media_type', defaultStatus: 415, title: 'Unsupported media type.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: OperationsErrorCode::ValidationFailed, type: 'urn:corex:problem:operations.validation_failed', defaultStatus: 422, title: 'Validation failed.', retryable: false, category: ErrorCategory::Validation, detailKeys: ['*']);
    }
}
