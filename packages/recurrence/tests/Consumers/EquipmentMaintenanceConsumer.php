<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Tests\Consumers;

use CoreX\Recurrence\Contracts\RecurrenceExpander;
use CoreX\Recurrence\Data\DateStart;
use CoreX\Recurrence\Data\ExpansionLimits;
use CoreX\Recurrence\Data\RecurrenceSet;
use CoreX\Recurrence\Data\RecurrenceWindow;
use CoreX\Recurrence\Internal\RuntimeProfile;

final class EquipmentMaintenanceConsumer
{
    public function __construct(private readonly RecurrenceExpander $expander) {}

    /** @return list<string> */
    public function plannedServiceDates(string $equipmentId): array
    {
        $set = RecurrenceSet::create(
            seriesId: $equipmentId,
            immutableRevision: 'maintenance-v1',
            start: new DateStart(valueType: 'DATE', nominalStart: '2026-01-31', tzid: null),
            rrule: 'FREQ=MONTHLY;COUNT=4',
            tzdbVersion: RuntimeProfile::tzdbVersion(),
        );

        $page = $this->expander->expand(
            set: $set,
            window: new RecurrenceWindow(valueType: 'DATE', start: '2026-01-01', end: '2026-08-01'),
            limits: new ExpansionLimits,
        );

        return array_map(static fn ($occurrence): string => $occurrence->effectiveDateOrInstant, $page->occurrences);
    }
}
