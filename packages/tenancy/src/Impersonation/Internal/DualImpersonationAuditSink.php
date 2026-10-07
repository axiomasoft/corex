<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Impersonation\Internal;

use Closure;
use CoreX\Audit\Contracts\SynchronousAuditWriter;
use CoreX\Audit\Data\AuditEntry;
use CoreX\Audit\Enums\ActorType;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Data\JsonDocument;
use CoreX\Tenancy\Impersonation\Contracts\ImpersonationAuditSink;
use CoreX\Tenancy\Impersonation\Data\ImpersonationAuditEvent;
use Illuminate\Database\ConnectionResolverInterface;
use RuntimeException;

/** @internal */
final readonly class DualImpersonationAuditSink implements ImpersonationAuditSink
{
    /** @param Closure(): string $id */
    public function __construct(
        private ConnectionResolverInterface $connections,
        private SynchronousAuditWriter $writer,
        private TenantContextResolver $contexts,
        private Closure $id,
        private string $centralConnection,
    ) {}

    public function record(ImpersonationAuditEvent $event): void
    {
        $context = $this->contexts->current();

        if ($context->account->id !== $event->accountId) {
            throw new RuntimeException('Impersonation audit account mismatch');
        }

        $central = $this->connections->connection($this->centralConnection);
        $tenant = $event->profile->mirrorsToTenant() ? $this->connections->connection('tenant') : null;

        if ($central->transactionLevel() !== 0 || ($tenant !== null && $tenant->transactionLevel() !== 0)) {
            throw new RuntimeException('Impersonation audit forbids ambient transactions');
        }

        $payload = [
            'grant_id' => $event->grantId,
            'account_id' => $event->accountId,
            'staff_identity_id' => $event->staffIdentityId,
            'target_user_id' => $event->targetUserId,
            'reason' => $event->reason,
            'profile' => $event->profile->value,
            'outcome' => $event->event,
            'expires_at' => $event->expiresAt?->format(DATE_ATOM),
        ];

        // Central intent commits first. A failed tenant mirror must not erase it.
        $central->transaction(function () use ($event, $payload, $central): void {
            if (! $central->table('root_account_events')->insert([
                'id' => ($this->id)(),
                'account_id' => $event->accountId,
                'event' => $event->event,
                'actor' => json_encode(['type' => 'support', 'id' => $event->staffIdentityId, 'grant' => $event->grantId], JSON_THROW_ON_ERROR),
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'created_at' => $event->occurredAt,
                'updated_at' => $event->occurredAt,
            ])) {
                throw new RuntimeException('Central impersonation audit insert failed');
            }
        });

        if ($tenant !== null) {
            $this->writer->record(entry: new AuditEntry(
                action: $event->event,
                actorType: ActorType::Support,
                actorId: $event->staffIdentityId,
                entityType: 'impersonation_grant',
                entityId: $event->grantId,
                context: new JsonDocument(json_encode($payload, JSON_THROW_ON_ERROR)),
                occurredAt: $event->occurredAt,
                workspaceId: $context->workspace?->id,
            ), connectionName: 'tenant');
        }
    }
}
