<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface ControlStoreIsolation
{
    /** Verifies this live control connection against the host's independent backup topology. */
    public function verify(ConnectionInterface $control, string $deploymentId): void;
}
