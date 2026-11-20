<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Errors\Enums;

use Packman\Attributes\Api;
use Packman\Errors\Contracts\ErrorCode;
use Packman\Errors\Enums\ErrorCategory;

#[Api(since: '2.0.0')]
enum CustomFieldsErrorCode: string implements ErrorCode
{
    case SchemaRevisionMismatch = 'custom-fields.schema_revision_mismatch';
    case DocumentRevisionMismatch = 'custom-fields.document_revision_mismatch';
    case IdempotencyConflict = 'custom-fields.idempotency_conflict';
    case ClaimLost = 'custom-fields.claim_lost';
    case AccessDenied = 'custom-fields.access_denied';
    case FieldTypeUnavailable = 'custom-fields.field_type_unavailable';
    case InvalidFieldValue = 'custom-fields.invalid_field_value';

    public function value(): string
    {
        return $this->value;
    }

    public function category(): ErrorCategory
    {
        return match ($this) {
            self::AccessDenied => ErrorCategory::Authorization,
            self::FieldTypeUnavailable => ErrorCategory::Unavailable,
            self::InvalidFieldValue => ErrorCategory::Validation,
            default => ErrorCategory::Conflict,
        };
    }
}
