<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Contracts;

use CoreX\Tenancy\TenantContext;
use CoreX\Wakeup\Data\SweepResult;
use DateTimeImmutable;

interface DueWakeupSweeper
{
    public function sweep(TenantContext $context, int $limit): SweepResult;

    public function nextDue(TenantContext $context): ?DateTimeImmutable;
}
