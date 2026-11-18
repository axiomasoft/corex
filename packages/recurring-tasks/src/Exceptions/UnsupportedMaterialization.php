<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Exceptions;

use CoreX\RecurringTasks\Errors\Enums\RecurringTasksErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

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
