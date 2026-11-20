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
                ? new ProviderObservation(ProviderObservationKind::Absent, 'owner-send-not-started', $this->owner())
                : null;
        }
        $channel = NotificationValue::text(NotificationValue::field($row, 'channel'));
        $driver = $this->drivers->find($channel);
        $descriptor = $driver?->descriptor();
        $version = NotificationValue::text(NotificationValue::field($row, 'driver_version'));

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
            attemptEpoch: NotificationValue::integer(NotificationValue::field($row, 'claim_epoch')),
            target: new DeliveryTarget($channel, NotificationValue::text(NotificationValue::field($row, 'target_reference'))),
            message: JsonDocuments::capture(NotificationValue::map(json_decode(NotificationValue::text(NotificationValue::field($row, 'message')), true, flags: JSON_THROW_ON_ERROR))),
            context: new NotificationContext($this->tenants->current(), $scope->principalKind, $scope->principalId),
            messageSha256: NotificationValue::text(NotificationValue::field($row, 'message_hash')),
            templateVersion: NotificationValue::text(NotificationValue::field($row, 'template_version')),
            policyVersion: NotificationValue::text(NotificationValue::field($row, 'policy_version')),
            driverVersion: $version,
        ));

        return DeliveryCoordinator::observation($outcome, $status->effect->effectKey);
    }

    public function project(ProviderEffectStatus $status): ?string
    {
        $observation = $status->observation;

        if ($observation === null || $observation->kind === ProviderObservationKind::Unknown) {
            return null;
        }

        $outcome = match ($observation->kind) {
            ProviderObservationKind::Accepted => new DeliveryOutcome(DeliveryOutcomeKind::Accepted, $observation->reference, null, 'provider-accepted'),
            ProviderObservationKind::Confirmed => new DeliveryOutcome(DeliveryOutcomeKind::Completed, $observation->reference, null, 'provider-confirmed'),
            ProviderObservationKind::Permanent => new DeliveryOutcome(DeliveryOutcomeKind::Permanent, null, null, 'provider-permanent'),
            ProviderObservationKind::Absent => new DeliveryOutcome(DeliveryOutcomeKind::Retryable, null, $this->retryDelay($observation->reference), 'provider-absent', true),
        };

        return $this->deliveries->project($status, $outcome);
    }

    private function retryDelay(string $reference): int
    {
        if (preg_match('/\Aretry-after-([0-9]{1,3})\z/D', $reference, $matches) === 1) {
            return max(1, min(300, (int) $matches[1]));
        }

        return 60;
    }
}
