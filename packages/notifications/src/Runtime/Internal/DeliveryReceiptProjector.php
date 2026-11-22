<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal;

use CoreX\Context\Contracts\ProviderReceiptProjector;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\ProviderEffectStatus;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Delivery\Data\DeliveryInput;
use CoreX\Notifications\Delivery\Data\DeliveryOutcome;
use CoreX\Notifications\Delivery\Data\DeliveryTarget;
use CoreX\Notifications\Delivery\Enums\DeliveryOutcomeKind;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use Packman\Attributes\Internal;

#[Internal]
final readonly class DeliveryReceiptProjector implements ProviderReceiptProjector
{
    public function __construct(
        private DeliveryStore $deliveries,
        private ChannelDrivers $drivers,
        private TenantContextResolver $tenants,
    ) {}

    public function owner(): string
    {
        return DeliveryIntents::Owner;
    }

    public function lookup(ProviderEffectStatus $status): ?ProviderObservation
    {
        $row = $this->deliveries->providerRow($status);

        if ($row === null) {
            return $this->deliveries->claimPrecededSend($status)
                ? new ProviderObservation(kind: ProviderObservationKind::Absent, reference: 'owner-send-not-started', observer: $this->owner())
                : null;
        }
        $channel = NotificationValue::text(NotificationValue::field(row: $row, name: 'channel'));
        $driver = $this->drivers->find($channel);
        $descriptor = $driver?->descriptor();
        $version = NotificationValue::text(NotificationValue::field(row: $row, name: 'driver_version'));

        if ($driver === null || $descriptor === null || ! $descriptor->supportsReconciliation || $descriptor->version !== $version) {
            return null;
        }
        $namespace = 'notification.'.hash('sha256', json_encode([$descriptor->key, $descriptor->version, $descriptor->dedupScope], JSON_THROW_ON_ERROR));

        if ($namespace !== $status->effect->providerNamespace) {
            return null;
        }

        $scope = $status->ticket->scope;
        $outcome = $driver->reconcile(new DeliveryInput(
            deliveryKey: $status->effect->effectKey,
            attemptEpoch: NotificationValue::integer(NotificationValue::field(row: $row, name: 'claim_epoch')),
            target: new DeliveryTarget(channel: $channel, reference: NotificationValue::text(NotificationValue::field(row: $row, name: 'target_reference'))),
            message: JsonDocuments::capture(NotificationValue::map(json_decode(NotificationValue::text(NotificationValue::field(row: $row, name: 'message')), true, flags: JSON_THROW_ON_ERROR))),
            context: new NotificationContext(tenant: $this->tenants->current(), actorKind: $scope->principalKind, actorId: $scope->principalId),
            messageSha256: NotificationValue::text(NotificationValue::field(row: $row, name: 'message_hash')),
            templateVersion: NotificationValue::text(NotificationValue::field(row: $row, name: 'template_version')),
            policyVersion: NotificationValue::text(NotificationValue::field(row: $row, name: 'policy_version')),
            driverVersion: $version,
        ));

        return DeliveryCoordinator::observation(outcome: $outcome, effectKey: $status->effect->effectKey);
    }

    public function project(ProviderEffectStatus $status): ?string
    {
        $observation = $status->observation;

        if ($observation === null || $observation->kind === ProviderObservationKind::Unknown) {
            return null;
        }

        $outcome = match ($observation->kind) {
            ProviderObservationKind::Accepted => new DeliveryOutcome(kind: DeliveryOutcomeKind::Accepted, providerRef: $observation->reference, retryAfter: null, reason: 'provider-accepted'),
            ProviderObservationKind::Confirmed => new DeliveryOutcome(kind: DeliveryOutcomeKind::Completed, providerRef: $observation->reference, retryAfter: null, reason: 'provider-confirmed'),
            ProviderObservationKind::Permanent => new DeliveryOutcome(kind: DeliveryOutcomeKind::Permanent, providerRef: null, retryAfter: null, reason: 'provider-permanent'),
            ProviderObservationKind::Absent => new DeliveryOutcome(kind: DeliveryOutcomeKind::Retryable, providerRef: null, retryAfter: $this->retryDelay($observation->reference), reason: 'provider-absent', absenceProven: true),
        };

        return $this->deliveries->project(status: $status, outcome: $outcome);
    }

    private function retryDelay(string $reference): int
    {
        if (preg_match('/\Aretry-after-([0-9]{1,3})\z/D', $reference, $matches) === 1) {
            return max(1, min(300, (int) $matches[1]));
        }

        return 60;
    }
}
