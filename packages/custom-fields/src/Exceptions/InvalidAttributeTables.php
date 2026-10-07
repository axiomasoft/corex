<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Exceptions;

use CoreX\Attributes\Api;
use CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class InvalidAttributeTables extends CodedException
{
    public static function invalidIdentifier(string $property, string $table): self
    {
        return new self("Attribute table [{$property}] must be a lowercase PostgreSQL identifier; [{$table}] is invalid.");
    }

    public static function duplicateNames(): self
    {
        return new self('Attribute table names must be unique across the configured table map.');
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: CustomFieldsErrorCode::InvalidAttributeTables, category: ErrorCategory::Internal, retryable: false);
    }
}
