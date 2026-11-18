<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Laravel;

use CoreX\Recurrence\Contracts\RecurrenceExpander;
use CoreX\Recurrence\Internal\RRuleRecurrenceExpander;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Default recurrence engine factory for host composition; the library itself needs no Laravel container. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class RecurrenceFactory
{
    public static function create(): RecurrenceExpander
    {
        return new RRuleRecurrenceExpander;
    }
}
