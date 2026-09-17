<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Contracts;

use CoreX\Recurrence\Data\ExpansionCursor;
use CoreX\Recurrence\Data\ExpansionLimits;
use CoreX\Recurrence\Data\OccurrencePage;
use CoreX\Recurrence\Data\RecurrenceSet;
use CoreX\Recurrence\Data\RecurrenceWindow;

interface RecurrenceExpander
{
    public function expand(RecurrenceSet $set, RecurrenceWindow $window, ExpansionLimits $limits, ?ExpansionCursor $after = null): OccurrencePage;
}
