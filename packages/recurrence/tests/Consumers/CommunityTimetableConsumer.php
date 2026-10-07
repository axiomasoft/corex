<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Tests\Consumers;

use CoreX\Recurrence\Contracts\RecurrenceExpander;
use CoreX\Recurrence\Data\DateStart;
use CoreX\Recurrence\Data\ExpansionLimits;
use CoreX\Recurrence\Data\RecurrenceSet;
use CoreX\Recurrence\Data\RecurrenceWindow;
use CoreX\Recurrence\Internal\RuntimeProfile;
use DateTimeImmutable;

final readonly class CommunityTimetableConsumer
{
    public function __construct(private RecurrenceExpander $expander) {}

    /** @return list<string> */
    public function weeklySessionOffsets(): array
    {
        $set = RecurrenceSet::create(
            seriesId: 'community-hall',
            immutableRevision: 'timetable-v1',
            start: new DateStart(valueType: 'TZID', nominalStart: '2026-03-01T09:00:00', tzid: 'America/New_York'),
            rrule: 'FREQ=WEEKLY;COUNT=3',
            tzdbVersion: RuntimeProfile::tzdbVersion(),
        );

        $page = $this->expander->expand(
            set: $set,
            window: new RecurrenceWindow(valueType: 'UTC', start: '2026-03-01T00:00:00Z', end: '2026-04-01T00:00:00Z'),
            limits: new ExpansionLimits,
        );

        return array_map(static function ($occurrence): string {
            $instant = new DateTimeImmutable($occurrence->effectiveDateOrInstant);

            return $instant->format('H:i P');
        }, $page->occurrences);
    }
}
