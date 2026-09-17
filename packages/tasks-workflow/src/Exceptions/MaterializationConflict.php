<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Exceptions;

use RuntimeException;

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
