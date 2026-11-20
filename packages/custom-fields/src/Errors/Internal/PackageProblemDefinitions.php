<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Errors\Internal;

use CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode;
use Packman\Attributes\Internal;
use Packman\Errors\Contracts\ProblemDefinitionProvider;
use Packman\Errors\Data\ProblemDefinition;
use Packman\Errors\Enums\ErrorCategory;

#[Internal]
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(code: CustomFieldsErrorCode::SchemaRevisionMismatch, type: 'urn:packman:problem:custom-fields.schema_revision_mismatch', defaultStatus: 409, title: 'Schema revision mismatch.', retryable: false, category: ErrorCategory::Conflict);
        yield new ProblemDefinition(code: CustomFieldsErrorCode::DocumentRevisionMismatch, type: 'urn:packman:problem:custom-fields.document_revision_mismatch', defaultStatus: 409, title: 'Document revision mismatch.', retryable: false, category: ErrorCategory::Conflict);
        yield new ProblemDefinition(code: CustomFieldsErrorCode::IdempotencyConflict, type: 'urn:packman:problem:custom-fields.idempotency_conflict', defaultStatus: 409, title: 'Idempotency conflict.', retryable: false, category: ErrorCategory::Conflict);
        yield new ProblemDefinition(code: CustomFieldsErrorCode::ClaimLost, type: 'urn:packman:problem:custom-fields.claim_lost', defaultStatus: 409, title: 'Claim lost.', retryable: false, category: ErrorCategory::Conflict);
        yield new ProblemDefinition(code: CustomFieldsErrorCode::AccessDenied, type: 'urn:packman:problem:custom-fields.access_denied', defaultStatus: 403, title: 'Access denied.', retryable: false, category: ErrorCategory::Authorization);
        yield new ProblemDefinition(code: CustomFieldsErrorCode::FieldTypeUnavailable, type: 'urn:packman:problem:custom-fields.field_type_unavailable', defaultStatus: 503, title: 'Field type unavailable.', retryable: false, category: ErrorCategory::Unavailable);
        yield new ProblemDefinition(code: CustomFieldsErrorCode::InvalidFieldValue, type: 'urn:packman:problem:custom-fields.invalid_field_value', defaultStatus: 422, title: 'Invalid field value.', retryable: false, category: ErrorCategory::Validation);
    }
}
