<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\DescribesError;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Tasks\Errors\Enums\TasksErrorCode;
use DomainException;

#[Api(since: '1.0')]
final class InvalidPlanningDraft extends DomainException implements DescribesError
{
    public static function forField(string $field, string $message): self
    {
        return new self("{$field}: {$message}");
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: TasksErrorCode::InvalidPlanningDraft, category: ErrorCategory::Validation, retryable: false);
    }
}
