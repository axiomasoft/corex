<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Exceptions;

use CoreX\Attributes\Api;
use RuntimeException;

#[Api(since: '1.0')]
final class MaterializationConflict extends RuntimeException
{
    public static function forOperationKey(string $operationKey): self
    {
        return new self("Materialization operation [{$operationKey}] conflicts with a stored receipt.");
    }

    public static function forGeneration(string $seriesKey): self
    {
        return new self("Series state [{$seriesKey}] changed during materialization.");
    }
}
