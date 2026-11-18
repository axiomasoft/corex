<?php

declare(strict_types=1);

namespace CoreX\Scheduling\Contracts;

use CoreX\Context\Data\TenantContext;
use CoreX\Scheduling\Data\SweepResult;
use DateTimeImmutable;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Delivers the due wakeups of one tenant; implemented by the scheduling backend. */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DueWakeupSweeper
{
    public function sweep(TenantContext $context, int $limit): SweepResult;

    public function nextDue(TenantContext $context): ?DateTimeImmutable;
}
