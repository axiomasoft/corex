<?php

declare(strict_types=1);

namespace CoreX\Notifications\Delivery\Internal;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Delivery\Contracts\DeliveryExecution;
use CoreX\Notifications\Delivery\Contracts\RecipientResolver;
use CoreX\Notifications\Delivery\Data\DeliveryInput;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Inbox\Contracts\NotificationPolicy;
use CoreX\Notifications\Inbox\Data\NotificationAction;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Data\NotificationSubject;
use Throwable;

final class DeliveryCoordinator implements DeliveryExecution
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

        $recipient = JsonDocuments::map($claim->message)['recipient'] ?? null;

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
                message: JsonDocuments::capture(JsonDocuments::map($claim->message)),
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
