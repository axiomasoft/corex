<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Context\Data\ProviderEffectStatus;
use CoreX\Context\Data\TenantContext;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface ProviderEffectRecovery
{
    public function assertReceipt(ProviderEffectStatus $status, ConnectionInterface $connection): TenantContext;

    public function recover(ProviderEffectRef $effect): bool;
}
