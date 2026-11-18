<?php

declare(strict_types=1);

namespace CoreX\Scheduling\Contracts;

use CoreX\Context\Data\TenantContext;
use CoreX\Scheduling\Data\SweepResult;
use DateTimeImmutable;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DueWakeupSource
{
    public const string TAG = 'corex.scheduling.due-sources';

    public function nextDue(TenantContext $context): ?DateTimeImmutable;

    public function sweep(TenantContext $context, DateTimeImmutable $now, int $limit): SweepResult;
}
