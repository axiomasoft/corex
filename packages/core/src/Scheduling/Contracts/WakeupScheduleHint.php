<?php

declare(strict_types=1);

namespace CoreX\Scheduling\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\TenantContext;
use DateTimeImmutable;

/** Told after a wakeup is scheduled, e.g. to keep a cross-tenant due index. Tag implementations with TAG. */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WakeupScheduleHint
{
    public const string TAG = 'corex.wakeup.hints';

    public function scheduled(TenantContext $context, DateTimeImmutable $fireAt): void;
}
