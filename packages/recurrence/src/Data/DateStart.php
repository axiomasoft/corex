<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class DateStart
{
    public function __construct(
        public string $valueType,
        public string $nominalStart,
        public ?string $tzid,
    ) {
        if (! in_array($valueType, ['DATE', 'TZID', 'UTC'], true)) {
            throw new InvalidArgumentException('The recurrence start value type is invalid.');
        }

        if ($valueType === 'TZID' && ($tzid === null || $tzid === '')) {
            throw new InvalidArgumentException('TZID recurrence requires a timezone identifier.');
        }

        if ($valueType === 'DATE' && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $nominalStart) !== 1) {
            throw new InvalidArgumentException('DATE recurrence requires a calendar date start.');
        }
    }
}
