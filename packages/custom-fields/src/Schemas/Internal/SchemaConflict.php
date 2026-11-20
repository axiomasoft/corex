<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode;
use Packman\Attributes\Internal;
use Packman\Errors\Contracts\DescribesError;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use RuntimeException;

#[Internal]
final class SchemaConflict extends RuntimeException implements DescribesError
{
    public function __construct(string $reason, private readonly bool $keyConflict = false)
    {
        parent::__construct($reason);
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: $this->keyConflict ? CustomFieldsErrorCode::IdempotencyConflict : CustomFieldsErrorCode::SchemaRevisionMismatch,
            category: ErrorCategory::Conflict, retryable: false);
    }
}
