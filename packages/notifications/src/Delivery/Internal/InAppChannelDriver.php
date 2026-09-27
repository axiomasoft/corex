<?php

declare(strict_types=1);

namespace CoreX\Notifications\Internal;

use CoreX\Notifications\Contracts\NotificationChannelDriver;
use CoreX\Notifications\Data\ChannelDescriptor;
use CoreX\Notifications\Data\DeliveryInput;
use CoreX\Notifications\Data\DeliveryOutcome;

final class InAppChannelDriver implements NotificationChannelDriver
{
    public function descriptor(): ChannelDescriptor
    {
        return new ChannelDescriptor(
            key: 'in_app',
            version: '1',
            supportsIdempotency: true,
            supportsReconciliation: true,
            dedupWindowSeconds: null,
            dedupScope: 'delivery_key',
        );
    }

    public function send(DeliveryInput $input): DeliveryOutcome
    {
        return new DeliveryOutcome(kind: 'accepted', providerRef: $input->deliveryKey, retryAfter: null, reason: 'stored-in-app');
    }

    public function reconcile(DeliveryInput $input): DeliveryOutcome
    {
        return new DeliveryOutcome(kind: 'accepted', providerRef: $input->deliveryKey, retryAfter: null, reason: 'stored-in-app');
    }
}
