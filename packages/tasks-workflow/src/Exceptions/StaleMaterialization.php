<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;
use CoreX\TasksWorkflow\Errors\Enums\TasksWorkflowErrorCode;

#[Api(since: '1.0')]
final class StaleMaterialization extends CodedException
{
    public static function revision(string $seriesKey, string $expected, string $actual): self
    {
        return new self("Series [{$seriesKey}] revision mismatch: expected [{$expected}], actual [{$actual}].");
    }

    public static function definition(string $seriesKey): self
    {
        return new self("Series [{$seriesKey}] definition hash no longer matches the admitted revision.");
    }

    public static function restoreEpoch(string $seriesKey): self
    {
        return new self("Series [{$seriesKey}] restore epoch is stale for the current worker.");
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(TasksWorkflowErrorCode::StaleMaterialization, ErrorCategory::Conflict, retryable: false);
    }
}
