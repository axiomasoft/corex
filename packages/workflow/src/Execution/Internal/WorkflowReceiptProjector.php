<?php

declare(strict_types=1);

namespace CoreX\Workflow\Execution\Internal;

use CoreX\Context\Contracts\ProviderEffectRecovery;
use CoreX\Context\Contracts\ProviderReceiptProjector;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\ProviderEffectStatus;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Workflow\Contracts\EngineAdapter;
use CoreX\Workflow\Data\EngineEffectRef;
use CoreX\Workflow\Enums\StartOutcome;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class WorkflowReceiptProjector implements ProviderReceiptProjector
{
    public function __construct(
        private DatabaseManager $database,
        private ProviderEffectRecovery $recovery,
        private ScopeAdmission $admission,
        private WorkflowIntentStore $intents,
        private StartIntentWorker $worker,
        private EngineAdapter $engine,
        private ClockInterface $clock,
    ) {}

    public function owner(): string
    {
        return WorkflowIntentStore::OWNER;
    }

    public function lookup(ProviderEffectStatus $status): ?ProviderObservation
    {
        $db = $this->database->connection();
        $scope = $status->ticket->scope;
        $context = $this->recovery->assertReceipt(status: $status, connection: $db);
        $row = $this->intent(db: $db, status: $status)->first();

        if ($row === null) {
            return null;
        }

        try {
            $request = $this->worker->request(context: $context, scope: $scope, row: $row, digest: $status->digest, effectKey: $status->effect->effectKey);
        } catch (UnsupportedWorkflowCapability) {
            return null;
        }
        $provider = $this->engine->provider($request->definition());

        if ($provider === null || $provider->namespace !== $status->effect->providerNamespace || ! $provider->statusLookup) {
            return null;
        }

        $instanceRef = $row->provider_instance;
        $accepted = $status->observation;

        if ($accepted?->kind === ProviderObservationKind::Accepted && $instanceRef !== $accepted->reference) {
            // Keep the original accepted identity before lookup can advance the authority's observation.
            $this->project($status);
            $instanceRef = $this->intent(db: $db, status: $status)->value('provider_instance');

            if ($instanceRef !== $accepted->reference) {
                return null;
            }
        }

        $result = $this->engine->reconcile(new EngineEffectRef(scope: $scope, providerNamespace: $provider->namespace, effectKey: $status->effect->effectKey, instanceRef: is_string($instanceRef) ? $instanceRef : null));
        $kind = match ($result->status) {
            StartOutcome::Confirmed => ProviderObservationKind::Confirmed,
            StartOutcome::Accepted => ProviderObservationKind::Accepted,
            StartOutcome::Permanent => ProviderObservationKind::Permanent,
            StartOutcome::AbsentWithProof => ProviderObservationKind::Absent,
            StartOutcome::Unknown, StartOutcome::RetryableBeforeSend => null,
        };
        $ref = $result->instanceRef ?? $result->errorCode;

        return $kind !== null && $ref !== null ? new ProviderObservation(kind: $kind, reference: $ref, observer: $provider->namespace) : null;
    }

    public function project(ProviderEffectStatus $status): ?string
    {
        $db = $this->database->connection();
        $quarantined = false;
        $receipt = $db->transaction(function () use ($db, $status, &$quarantined): ?string {
            $this->recovery->assertReceipt(status: $status, connection: $db);
            $query = $this->intent(db: $db, status: $status);
            $row = (clone $query)->lockForUpdate()->first();

            if ($row === null) {
                return null;
            }

            $now = WorkflowIntentStore::time($this->clock->now());
            $available = $query->where(static fn (Builder $lease) => $lease->whereNull('lease_until')->orWhere('lease_until', '<=', $now));

            if ($status->state === 'conflicting') {
                $available->whereIn('state', ['claimed', 'sending', 'unknown', 'accepted', 'confirmed', 'failed', 'retry-scheduled'])
                    ->update(WorkflowIntentStore::quarantineChanges($status->firstProviderRef) + [
                        'provider_ticket' => $status->ticket->id, 'updated_at' => $now,
                    ]);

                return null;
            }

            $observation = $status->observation;

            if ($observation === null || $observation->kind === ProviderObservationKind::Unknown) {
                return null;
            }

            $liveReceipt = $available->whereIn('state', ['claimed', 'sending', 'unknown', 'accepted']);

            if (WorkflowIntentStore::instanceConflict(row: $row, observation: $observation, firstProviderRef: $status->firstProviderRef)) {
                $quarantined = $liveReceipt->update(WorkflowIntentStore::quarantineChanges($status->firstProviderRef) + [
                    'provider_ticket' => $status->ticket->id, 'updated_at' => $now,
                ]) === 1;

                return null;
            }

            $state = match ($observation->kind) {
                ProviderObservationKind::Accepted => 'accepted',
                ProviderObservationKind::Confirmed => 'confirmed',
                ProviderObservationKind::Absent => 'retry-scheduled',
                ProviderObservationKind::Permanent => 'failed',
            };

            if ($row->state === $state && $row->provider_receipt === $observation->reference) {
                return $state === 'accepted' ? null : $status->ownerRef;
            }

            $instance = $state === 'confirmed' || $state === 'accepted' ? $observation->reference : $row->provider_instance;
            $changed = $liveReceipt->update([
                'state' => $state, 'provider_ticket' => $status->ticket->id,
                'provider_instance' => $instance, 'provider_receipt' => $observation->reference,
                'lease_until' => null, 'updated_at' => $now,
                'due_at' => in_array($state, ['accepted', 'retry-scheduled'], true) ? $now : null,
            ]);

            return $changed === 1 && $state !== 'accepted' ? $status->ownerRef : null;
        });

        if ($quarantined && $status->state === 'outstanding') {
            $this->admission->settle(ticket: $status->ticket, evidence: new AttemptEvidence(outcome: 'unknown', reference: $status->ownerRef));
        }

        return $receipt;
    }

    private function intent(Connection $db, ProviderEffectStatus $status): Builder
    {
        return $this->intents->scoped(db: $db, scope: $status->ticket->scope)->where([
            'id' => $status->ownerRef,
            'payload_hash' => $status->digest,
            'claim_token' => $status->claimToken,
            'provider_namespace' => $status->effect->providerNamespace,
            'effect_key' => $status->effect->effectKey,
        ])->where(static fn (Builder $ticket) => $ticket->whereNull('provider_ticket')->orWhere('provider_ticket', $status->ticket->id));
    }
}
