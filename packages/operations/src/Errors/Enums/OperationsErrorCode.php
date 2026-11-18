<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Enums;

use Packman\Attributes\Api;
use Packman\Errors\Contracts\ErrorCode;

#[Api(since: '1.0')]
enum OperationsErrorCode: string implements ErrorCode
{
    case AccessDenied = 'operations.access_denied';
    case AccountUnavailable = 'operations.account_unavailable';
    case AuthenticationRequired = 'operations.authentication_required';
    case BudgetRateLimited = 'operations.budget_rate_limited';
    case BudgetStoreUnavailable = 'operations.budget_store_unavailable';
    case BulkNotReady = 'operations.bulk_not_ready';
    case BulkReconciliationRequired = 'operations.bulk_reconciliation_required';
    case BulkResultExpired = 'operations.bulk_result_expired';
    case CapabilityUnavailable = 'operations.capability_unavailable';
    case CatalogueLimit = 'operations.catalogue_limit';
    case Conflict = 'operations.conflict';
    case ContextRequired = 'operations.context_required';
    case CursorInvalid = 'operations.cursor_invalid';
    case Forbidden = 'operations.forbidden';
    case IdempotencyConflict = 'operations.idempotency_conflict';
    case IdempotencyContractMismatch = 'operations.idempotency_contract_mismatch';
    case IdempotencyInProgress = 'operations.idempotency_in_progress';
    case IdempotencyKeyForbidden = 'operations.idempotency_key_forbidden';
    case IdempotencyKeyInvalid = 'operations.idempotency_key_invalid';
    case IdempotencyKeyRequired = 'operations.idempotency_key_required';
    case IdempotencyNotSupported = 'operations.idempotency_not_supported';
    case InvalidRequest = 'operations.invalid_request';
    case ModuleUnavailable = 'operations.module_unavailable';
    case NotFound = 'operations.not_found';
    case OperationNotFound = 'operations.operation_not_found';
    case OperationUnsupported = 'operations.operation_unsupported';
    case OrderingVersionUnsupported = 'operations.ordering_version_unsupported';
    case RateLimited = 'operations.rate_limited';
    case ReconciliationRequired = 'operations.reconciliation_required';
    case RequestRejected = 'operations.request_rejected';
    case ResourceNotFound = 'operations.resource_not_found';
    case ScopeRequired = 'operations.scope_required';
    case UnknownMessageContract = 'operations.unknown_message_contract';
    case ValidationFailed = 'operations.validation_failed';

    case InputEnvelopeInvalid = 'operations.input_envelope_invalid';
    case UndeclaredFields = 'operations.undeclared_fields';
    case UnsupportedMediaType = 'operations.unsupported_media_type';

    public function value(): string
    {
        return $this->value;
    }
}
