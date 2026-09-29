<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Contracts;

use CoreX\Context\Data\TenantContext;
use DateTimeImmutable;

interface WakeupScheduleHint
{
    public function scheduled(TenantContext $context, DateTimeImmutable $fireAt): void;
}
