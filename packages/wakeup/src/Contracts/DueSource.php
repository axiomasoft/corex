<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Contracts;

use CoreX\Tenancy\TenantContext;
use CoreX\Wakeup\Data\SweepResult;
use DateTimeImmutable;

interface DueSource
{
    public function nextDue(TenantContext $context): ?DateTimeImmutable;

    public function sweep(TenantContext $context, DateTimeImmutable $now, int $limit): SweepResult;
}
