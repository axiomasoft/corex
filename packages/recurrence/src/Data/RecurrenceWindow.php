<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class RecurrenceWindow
{
    public function __construct(
        public string $valueType,
        public string $start,
        public string $end,
    ) {
        if (! in_array($valueType, ['DATE', 'TZID', 'UTC'], true)) {
            throw new InvalidArgumentException('The recurrence window value type is invalid.');
        }

        if ($valueType === 'DATE') {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                throw new InvalidArgumentException('DATE windows require calendar dates.');
            }
        } else {
            if (! self::isUtcInstant($start) || ! self::isUtcInstant($end)) {
                throw new InvalidArgumentException('Timed windows require UTC instants.');
            }
        }

        if ($end <= $start) {
            throw new InvalidArgumentException('A recurrence window must be half-open and non-empty.');
        }
    }

    private static function isUtcInstant(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) === 1;
    }
}
