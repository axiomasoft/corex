<?php

declare(strict_types=1);

namespace CoreX\Notifications\Internal;

use CoreX\Notifications\Contracts\NotificationPolicy;
use CoreX\Notifications\Contracts\RecipientResolver;
use CoreX\Notifications\Data\DeliveryInput;
use CoreX\Notifications\Data\NotificationAction;
use CoreX\Notifications\Data\NotificationContext;
use CoreX\Notifications\Data\NotificationSubject;
use CoreX\Notifications\Data\RecipientReference;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use Throwable;

final class DeliveryCoordinator
{
    public function __construct(
        private readonly DeliveryStore $deliveries,
        private readonly ChannelDrivers $drivers,
        private readonly RecipientResolver $recipients,
        private readonly NotificationPolicy $policy,
        private readonly TenantContextResolver $tenants,
    ) {}

    public function deliver(string $deliveryId): void
    {
        $claim = $this->deliveries->claim($deliveryId);

        if ($claim === null) {
            return;
        }

        $recipient = $claim->message['recipient'] ?? null;

        if (! is_array($recipient) || ! isset($recipient['kind'], $recipient['id']) || ! is_string($recipient['kind']) || ! is_string($recipient['id'])) {
            $this->deliveries->skip($claim, 'invalid-recipient');

            return;
        }

        $context = new NotificationContext(tenant: $this->tenants->current(), actorKind: 'system', actorId: null);
        $subject = new NotificationSubject(notificationId: $claim->notificationId, recipient: new RecipientReference(kind: $recipient['kind'], id: $recipient['id']), related: null);

        if (! $this->policy->allows($context, NotificationAction::Notify, $subject)) {
            $this->deliveries->skip($claim, 'policy-denied');

            return;
        }

        $driver = $this->drivers->find($claim->channel);

        if ($driver === null) {
            $this->deliveries->skip($claim, 'provider-unconfigured');

            return;
        }

        $target = $this->recipients->resolve($context, $subject->recipient, $claim->channel);

        if ($target === null) {
            $this->deliveries->skip($claim, 'recipient-unavailable');

            return;
        }

        try {
            $this->deliveries->complete($claim, $driver->send(new DeliveryInput(
                deliveryKey: $claim->deliveryKey,
                attemptEpoch: $claim->epoch,
                target: $target,
                message: $claim->message,
                context: $context,
                messageSha256: $claim->messageHash,
                templateVersion: $claim->templateVersion,
                policyVersion: $claim->policyVersion,
                driverVersion: $claim->driverVersion,
            )));
        } catch (Throwable $exception) {
            $this->deliveries->markUnknown($claim, 'worker-exception');

            throw $exception;
        }
    }
}
