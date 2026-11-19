<?php

declare(strict_types=1);

namespace CoreX\Access\Contracts;

use CoreX\Context\Contracts\Principal;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** CoreX capability and record authorization for an active entity. */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface AccessChecker
{
    /** Capability check: can the principal do $action on the active entity type at all. */
    public function allowed(Principal $p, string $entityKey, string $action): bool;

    public function canOnRecord(Principal $p, string $action, object $record): bool;
}
