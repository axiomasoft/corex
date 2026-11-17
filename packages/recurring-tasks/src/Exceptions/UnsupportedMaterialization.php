<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;
use CoreX\RecurringTasks\Errors\Enums\RecurringTasksErrorCode;

#[Api(since: '1.0')]
final class UnsupportedMaterialization extends CodedException
{
    public static function connection(string $message): self
    {
        return new self($message);
    }

    public static function expansion(string $status): self
    {
        return new self("Recurrence expansion returned unsupported status [{$status}].");
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: RecurringTasksErrorCode::UnsupportedMaterialization, category: ErrorCategory::Unsupported, retryable: false);
    }
}
