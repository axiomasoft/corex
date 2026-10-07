<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Errors\Internal;

use CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode;
use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Data\ProblemDefinition;
use CoreX\Errors\Enums\ErrorCategory;

/** @internal The owning package contributes its safe error catalogue at boot. */
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(code: CustomFieldsErrorCode::AttributeSchemaConflict, type: 'urn:corex:problem:custom-fields.attribute_schema_conflict', defaultStatus: 409, title: 'Attribute schema conflict.', retryable: true, category: ErrorCategory::Conflict, detailKeys: ['reason']);
        yield new ProblemDefinition(code: CustomFieldsErrorCode::AttributeSchemaRetryExhausted, type: 'urn:corex:problem:custom-fields.attribute_schema_retry_exhausted', defaultStatus: 409, title: 'Attribute schema retry exhausted.', retryable: false, category: ErrorCategory::Conflict, detailKeys: ['reason']);
        yield new ProblemDefinition(code: CustomFieldsErrorCode::AttributesAccessDenied, type: 'urn:corex:problem:custom-fields.attributes_access_denied', defaultStatus: 403, title: 'Attributes access denied.', retryable: false, category: ErrorCategory::Authorization, detailKeys: ['reason']);
        yield new ProblemDefinition(code: CustomFieldsErrorCode::AttributesEngineUnavailable, type: 'urn:corex:problem:custom-fields.attributes_engine_unavailable', defaultStatus: 503, title: 'Attributes engine unavailable.', retryable: false, category: ErrorCategory::Unavailable, detailKeys: ['reason']);
        yield new ProblemDefinition(code: CustomFieldsErrorCode::InvalidAttributeTables, type: 'urn:corex:problem:custom-fields.invalid_attribute_tables', defaultStatus: 500, title: 'Invalid attribute tables.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
    }
}
