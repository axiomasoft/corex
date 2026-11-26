<?php

declare(strict_types=1);

namespace CoreX\Audit\Internal;

use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Audit\Data\AuditEntry;
use CoreX\Config\Data\CoreOptions;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Contracts\TenantContextResolver;
use Illuminate\Contracts\Bus\Dispatcher;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

/**
 * Synchronous enqueue path (the sync-path budget only dispatches; the row
 * is written by RecordAuditEntry on a worker).
 *
 * The event-time snapshot is taken HERE: occurredAt = the clock's now and
 * workspaceId = the CURRENT tenant context — both captured while the request
 * that produced the event is still alive. The worker only writes them; it no
 * longer resolves tenant at write time (an async worker runs under a different
 * or absent tenant context and would stamp a foreign workspace and the write
 * moment). Boxed/CLI installs (SingleAccountResolver) carry workspaceId = null
 * — an honest null, not a foreign tenant.
 *
 * @internal
 */
#[Internal]
final readonly class QueuedAuditLogger implements AuditLogger
{
    public function __construct(
        private CoreOptions $options,
        private ImpersonationService $impersonation,
        private TenantContextResolver $tenantContext,
        private ClockInterface $clock,
        private DataScopeFactory $scopes,
        private Dispatcher $bus,
    ) {}

    public function record(AuditEntry $entry): void
    {
        // Killswitch, re-checked at the WRITE seam (defense-in-depth):
        // AuditObserver::record() already guards, but this is
        // the AuditLogger contract — any caller resolving it directly (not via
        // the observer) would otherwise enqueue a row with audit disabled. The
        // gate lives at enqueue time, not in RecordAuditEntry::handle(): an
        // event captured while auditing was ON must still land if config flips
        // before the worker runs (the journal records what happened).
        if (! $this->options->auditEnabled) {
            return;
        }

        $state = $this->impersonation->state();

        if ($state !== null) {
            $entry = $entry->withImpersonation($state);
        }

        $current = $this->tenantContext->current();
        $scope = $this->scopes->forCurrentPrincipal($current);
        $entry = $entry->withSnapshot(occurredAt: $this->clock->now(), workspaceId: $current->workspace?->id);
        $this->bus->dispatch(new RecordAuditEntry(entry: $entry, scope: $scope));
    }
}
