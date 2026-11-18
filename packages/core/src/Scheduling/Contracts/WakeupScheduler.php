<?php

declare(strict_types=1);

namespace CoreX\Scheduling\Contracts;

use CoreX\Context\Data\TenantContext;
use CoreX\Scheduling\Data\CancelResult;
use CoreX\Scheduling\Data\ScheduleResult;
use CoreX\Scheduling\Data\WakeupRequest;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WakeupScheduler
{
    public function schedule(WakeupRequest $request): ScheduleResult;

    public function cancel(TenantContext $context, string $requestKey): CancelResult;
}
