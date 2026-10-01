<?php

declare(strict_types=1);

namespace CoreX\Notifications\Delivery\Data;

use CoreX\Attributes\Api;
use CoreX\Data\Data\JsonDocument;
use CoreX\Notifications\Inbox\Data\NotificationContext;

#[Api(since: '1.0')]
final readonly class DeliveryInput
{
    /** Immutable JSON value. */
    public function __construct(public string $deliveryKey, public int $attemptEpoch, public DeliveryTarget $target, public JsonDocument $message, public NotificationContext $context, public string $messageSha256, public string $templateVersion, public string $policyVersion, public string $driverVersion) {}
}
