<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Exceptions;

use CoreX\Tasks\Errors\Enums\TasksErrorCode;
use DomainException;
use Packman\Attributes\Api;
use Packman\Errors\Contracts\DescribesError;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;

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
