<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Contracts;

use CoreX\Context\Data\TenantContext;
use CoreX\Wakeup\Data\SweepResult;
use DateTimeImmutable;

interface DueWakeupSource
{
    public function nextDue(TenantContext $context): ?DateTimeImmutable;

    public function sweep(TenantContext $context, DateTimeImmutable $now, int $limit): SweepResult;
}
