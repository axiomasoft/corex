<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Contracts;

use CoreX\Tenancy\TenantContext;
use CoreX\Wakeup\Data\CancelResult;
use CoreX\Wakeup\Data\ScheduleResult;
use CoreX\Wakeup\Data\WakeupRequest;

interface WakeupScheduler
{
    public function schedule(WakeupRequest $request): ScheduleResult;

    public function cancel(TenantContext $context, string $requestKey): CancelResult;
}
