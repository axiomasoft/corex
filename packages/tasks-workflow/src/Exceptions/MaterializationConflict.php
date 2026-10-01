<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;
use CoreX\TasksWorkflow\Errors\Enums\TasksWorkflowErrorCode;

#[Api(since: '1.0')]
final class MaterializationConflict extends CodedException
{
    public static function forOperationKey(string $operationKey): self
    {
        return new self("Materialization operation [{$operationKey}] conflicts with a stored receipt.");
    }

    public static function forGeneration(string $seriesKey): self
    {
        return new self("Series state [{$seriesKey}] changed during materialization.");
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(TasksWorkflowErrorCode::MaterializationConflict, ErrorCategory::Conflict, retryable: false);
    }
}
