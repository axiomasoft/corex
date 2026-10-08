<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Scheduling\Contracts\WakeupScheduleHint as SchedulingWakeupScheduleHint;

/**
 * BC alias: existing implementers keep working.
 *
 * @deprecated Use {@see SchedulingWakeupScheduleHint}; removed in 2.0.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WakeupScheduleHint extends SchedulingWakeupScheduleHint {}
