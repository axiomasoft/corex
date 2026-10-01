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
final class PlanningConflict extends DomainException implements DescribesError
{
    public static function forRequestKey(string $requestKey): self
    {
        return new self("Planning request [{$requestKey}] already exists with a different payload.");
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(TasksErrorCode::PlanningConflict, ErrorCategory::Conflict, retryable: false);
    }
}
