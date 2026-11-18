<?php

declare(strict_types=1);

namespace CoreX\Audit\Internal;

use CoreX\Audit\Contracts\AuditRetention;
use DateTimeImmutable;
use Packman\Attributes\Internal;

/**
 * Boxed-safe default: no retention boundary — `corex:audit:prune` deletes
 * nothing. An append-only journal defaults to keeping everything; a host that
 * wants pruning binds its own AuditRetention (config `corex.bindings.audit_retention`).
 */
#[Internal]
final class NullAuditRetention implements AuditRetention
{
    public function olderThan(): ?DateTimeImmutable
    {
        return null;
    }
}
