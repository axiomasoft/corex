<?php

declare(strict_types=1);

namespace CoreX\Scheduling\Contracts;

use CoreX\Context\Data\TenantContext;
use DateTimeImmutable;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Told after a wakeup is scheduled, e.g. to keep a cross-tenant due index. Tag implementations with TAG. */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WakeupScheduleHint
{
    public const string TAG = 'corex.wakeup.hints';

    public function scheduled(TenantContext $context, DateTimeImmutable $fireAt): void;
}
