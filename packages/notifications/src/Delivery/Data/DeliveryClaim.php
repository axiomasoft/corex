<?php

declare(strict_types=1);

namespace CoreX\Notifications\Delivery\Data;

use CoreX\Attributes\Api;
use CoreX\Data\Data\JsonDocument;

#[Api(since: '1.0')]
final readonly class DeliveryClaim
{
    /** Immutable JSON value. */
    public function __construct(public string $id, public ?string $notificationId, public ?string $digestRunId, public string $channel, public string $deliveryKey, public int $epoch, public int $attempts, public JsonDocument $message, public string $messageHash, public string $templateVersion, public string $policyVersion, public string $driverVersion) {}
}
