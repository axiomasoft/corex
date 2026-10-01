<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\TenantContext;
use DateTimeImmutable;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WakeupScheduleHint
{
    public function scheduled(TenantContext $context, DateTimeImmutable $fireAt): void;
}
