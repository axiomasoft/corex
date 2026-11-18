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
final class PlanningConflict extends DomainException implements DescribesError
{
    public static function forRequestKey(string $requestKey): self
    {
        return new self("Planning request [{$requestKey}] already exists with a different payload.");
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: TasksErrorCode::PlanningConflict, category: ErrorCategory::Conflict, retryable: false);
    }
}
