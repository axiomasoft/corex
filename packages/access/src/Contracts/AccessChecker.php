<?php

declare(strict_types=1);

namespace CoreX\Access\Contracts;

use CoreX\Access\Enums\Scope;
use CoreX\Access\RecordContext;

/**
 * Two-phase permission check (B-11 §5.5): allowed() is the capability gate
 * (AzGuard cache, ≤1ms), scopeFor()/canOnRecord() apply the compiled
 * row-visibility scope on top. Implemented by P2.20's DefaultAccessChecker
 * over the schema/compiler produced by P2.8.
 *
 * @internal spec: B-11 §3.3
 */
interface AccessChecker
{
    /** Capability check (AzGuard cache): can the principal do $action on the $entityKey type at all. */
    public function allowed(Principal $p, string $entityKey, string $action): bool;

    /** Effective scope: max weight of the principal's base matrix rows; a $ctx row overrides (may narrow) it. */
    public function scopeFor(Principal $p, string $entityKey, string $action, ?RecordContext $ctx = null): Scope;

    /** Record-level check: allowed() plus the effective scope's predicate against $record, or an active AzGuard direct grant. */
    public function canOnRecord(Principal $p, string $action, object $record): bool;
}
