<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal;

use CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode;
use CoreX\Errors\Contracts\DescribesError;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use RuntimeException;

final class SchemaOperationConflict extends RuntimeException implements DescribesError
{
    private function __construct(
        string $message,
        private readonly ErrorDescriptor $descriptor,
    ) {
        parent::__construct($message);
    }

    public static function notReady(): self
    {
        return new self(
            'The schema operation is not ready for execution.',
            new ErrorDescriptor(
                code: CustomFieldsErrorCode::AttributeSchemaConflict,
                category: ErrorCategory::Conflict,
                retryable: true,
                details: ['reason' => 'not_ready'],
            ),
        );
    }

    public static function staleSnapshot(): self
    {
        return new self(
            'The schema operation was requested against a stale field snapshot.',
            new ErrorDescriptor(
                code: CustomFieldsErrorCode::AttributeSchemaConflict,
                category: ErrorCategory::Conflict,
                retryable: false,
                details: ['reason' => 'stale_snapshot'],
            ),
        );
    }

    public static function retryExhausted(): self
    {
        return new self(
            'The schema operation retry limit is exhausted.',
            new ErrorDescriptor(
                code: CustomFieldsErrorCode::AttributeSchemaRetryExhausted,
                category: ErrorCategory::Conflict,
                retryable: false,
                details: ['reason' => 'retry_exhausted'],
            ),
        );
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return $this->descriptor;
    }
}
