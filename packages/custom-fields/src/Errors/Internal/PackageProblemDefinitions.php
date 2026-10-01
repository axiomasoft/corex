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
        yield new ProblemDefinition(CustomFieldsErrorCode::AttributeSchemaConflict, 'urn:corex:problem:custom-fields.attribute_schema_conflict', 409, 'Attribute schema conflict.', true, ErrorCategory::Conflict, ['reason']);
        yield new ProblemDefinition(CustomFieldsErrorCode::AttributeSchemaRetryExhausted, 'urn:corex:problem:custom-fields.attribute_schema_retry_exhausted', 409, 'Attribute schema retry exhausted.', false, ErrorCategory::Conflict, ['reason']);
        yield new ProblemDefinition(CustomFieldsErrorCode::AttributesAccessDenied, 'urn:corex:problem:custom-fields.attributes_access_denied', 403, 'Attributes access denied.', false, ErrorCategory::Authorization, ['reason']);
        yield new ProblemDefinition(CustomFieldsErrorCode::AttributesEngineUnavailable, 'urn:corex:problem:custom-fields.attributes_engine_unavailable', 503, 'Attributes engine unavailable.', false, ErrorCategory::Unavailable, ['reason']);
        yield new ProblemDefinition(CustomFieldsErrorCode::InvalidAttributeTables, 'urn:corex:problem:custom-fields.invalid_attribute_tables', 500, 'Invalid attribute tables.', false, ErrorCategory::Internal, []);
    }
}
