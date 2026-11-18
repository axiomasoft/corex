<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Exceptions;

use CoreX\RecurringTasks\Errors\Enums\RecurringTasksErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

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
        return new ErrorDescriptor(code: RecurringTasksErrorCode::StaleMaterialization, category: ErrorCategory::Conflict, retryable: false);
    }
}
