<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Impersonation\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\Impersonation\Data\ImpersonationAuditEvent;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ImpersonationAuditSink
{
    /** Throw when persistence or commit is uncertain. */
    public function record(ImpersonationAuditEvent $event): void;
}
