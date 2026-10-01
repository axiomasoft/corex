<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\TenantContext;
use CoreX\Wakeup\Data\CancelResult;
use CoreX\Wakeup\Data\ScheduleResult;
use CoreX\Wakeup\Data\WakeupRequest;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WakeupScheduler
{
    public function schedule(WakeupRequest $request): ScheduleResult;

    public function cancel(TenantContext $context, string $requestKey): CancelResult;
}
