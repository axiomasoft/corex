<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Errors\Internal;

use CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode;
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
        yield new ProblemDefinition(code: CustomFieldsErrorCode::AttributeSchemaConflict, type: 'urn:packman:problem:custom-fields.attribute_schema_conflict', defaultStatus: 409, title: 'Attribute schema conflict.', retryable: true, category: ErrorCategory::Conflict, detailKeys: ['reason']);
        yield new ProblemDefinition(code: CustomFieldsErrorCode::AttributeSchemaRetryExhausted, type: 'urn:packman:problem:custom-fields.attribute_schema_retry_exhausted', defaultStatus: 409, title: 'Attribute schema retry exhausted.', retryable: false, category: ErrorCategory::Conflict, detailKeys: ['reason']);
        yield new ProblemDefinition(code: CustomFieldsErrorCode::AttributesAccessDenied, type: 'urn:packman:problem:custom-fields.attributes_access_denied', defaultStatus: 403, title: 'Attributes access denied.', retryable: false, category: ErrorCategory::Authorization, detailKeys: ['reason']);
        yield new ProblemDefinition(code: CustomFieldsErrorCode::AttributesEngineUnavailable, type: 'urn:packman:problem:custom-fields.attributes_engine_unavailable', defaultStatus: 503, title: 'Attributes engine unavailable.', retryable: false, category: ErrorCategory::Unavailable, detailKeys: ['reason']);
        yield new ProblemDefinition(code: CustomFieldsErrorCode::InvalidAttributeTables, type: 'urn:packman:problem:custom-fields.invalid_attribute_tables', defaultStatus: 500, title: 'Invalid attribute tables.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
    }
}
