<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Errors\Enums;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\ErrorCode;

#[Api(since: '1.0')]
enum CustomFieldsErrorCode: string implements ErrorCode
{
    case AttributeSchemaConflict = 'custom-fields.attribute_schema_conflict';
    case AttributeSchemaRetryExhausted = 'custom-fields.attribute_schema_retry_exhausted';
    case AttributesAccessDenied = 'custom-fields.attributes_access_denied';
    case AttributesEngineUnavailable = 'custom-fields.attributes_engine_unavailable';
    case InvalidAttributeTables = 'custom-fields.invalid_attribute_tables';

    public function value(): string
    {
        return $this->value;
    }
}
