<?php

declare(strict_types=1);

namespace CoreX\Tasks\Time\Internal;

use CoreX\Tasks\Time\Data\TimeLogReference;
use CoreX\Tasks\Time\Internal\Models\TimeLog;
use Packman\Attributes\Internal;

/** @internal */
#[Internal]
final class TimeLogReferenceFactory
{
    public static function fromTimeLog(TimeLog $timeLog): TimeLogReference
    {
        return new TimeLogReference(id: $timeLog->id, type: TimeLogReference::TYPE, display: sprintf('%dm on %s', $timeLog->minutes, $timeLog->spent_on->format('Y-m-d')));
    }
}
