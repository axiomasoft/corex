<?php

declare(strict_types=1);

namespace CoreX\Foundation\Contracts;

use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\PlanDigest;
use CoreX\Foundation\Data\RemovalApproval;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface ProviderRemovalGate
{
    public function prepare(ModuleRef $provider, PlanDigest $installed, PlanDigest $proposed): RemovalApproval;

    public function consume(RemovalApproval $approval, PlanDigest $installed): void;
}
