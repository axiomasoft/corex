<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\TenantContext;
use CoreX\Wakeup\Data\SweepResult;
use DateTimeImmutable;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DueWakeupSource
{
    public function nextDue(TenantContext $context): ?DateTimeImmutable;

    public function sweep(TenantContext $context, DateTimeImmutable $now, int $limit): SweepResult;
}
