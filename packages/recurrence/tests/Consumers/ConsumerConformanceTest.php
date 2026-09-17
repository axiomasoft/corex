<?php

declare(strict_types=1);

use CoreX\Recurrence\Internal\RRuleRecurrenceExpander;
use CoreX\Recurrence\Tests\Consumers\CommunityTimetableConsumer;
use CoreX\Recurrence\Tests\Consumers\EquipmentMaintenanceConsumer;

it('REC-12 equipment maintenance consumer uses only the recurrence expander port', function (): void {
    $consumer = new EquipmentMaintenanceConsumer(expander: new RRuleRecurrenceExpander);

    expect($consumer->plannedServiceDates(equipmentId: 'boiler-7'))
        ->toBe(['2026-01-31', '2026-03-31', '2026-05-31', '2026-07-31']);
});

it('REC-12 community timetable consumer uses only the recurrence expander port', function (): void {
    $consumer = new CommunityTimetableConsumer(expander: new RRuleRecurrenceExpander);

    expect($consumer->weeklySessionOffsets())
        ->toBe(['09:00 -05:00', '09:00 -04:00', '09:00 -04:00']);
});
