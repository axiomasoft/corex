<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Recurrence\Data\ExpansionCursor;
use CoreX\Recurrence\Data\ExpansionLimits;
use CoreX\Recurrence\Data\OccurrencePage;
use CoreX\Recurrence\Data\RecurrenceSet;
use CoreX\Recurrence\Data\RecurrenceWindow;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface RecurrenceExpander
{
    public function expand(RecurrenceSet $set, RecurrenceWindow $window, ExpansionLimits $limits, ?ExpansionCursor $after = null): OccurrencePage;
}
