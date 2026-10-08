<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Scheduling\Contracts\DueWakeupSweeper as SchedulingDueWakeupSweeper;

/**
 * BC alias: existing implementers and type hints keep working.
 *
 * @deprecated Use {@see SchedulingDueWakeupSweeper}; removed in 2.0.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DueWakeupSweeper extends SchedulingDueWakeupSweeper {}
