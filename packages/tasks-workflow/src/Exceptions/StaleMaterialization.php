<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Exceptions;

use RuntimeException;

final class StaleMaterialization extends RuntimeException
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
}
