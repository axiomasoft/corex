<?php

declare(strict_types=1);

namespace CoreX\Scheduling\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\TenantContext;
use CoreX\Scheduling\Data\CancelResult;
use CoreX\Scheduling\Data\ScheduleResult;
use CoreX\Scheduling\Data\WakeupRequest;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WakeupScheduler
{
    public function schedule(WakeupRequest $request): ScheduleResult;

    public function cancel(TenantContext $context, string $requestKey): CancelResult;
}
