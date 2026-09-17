<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Contracts;

use CoreX\Tenancy\TenantContext;
use DateTimeImmutable;

interface WakeupHint
{
    public function scheduled(TenantContext $context, DateTimeImmutable $fireAt): void;
}
